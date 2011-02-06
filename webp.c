/*
 * WebP image read/write functions
 *
 * Copyright (c) 2011 Ryusuke SEKIYAMA. All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * @package     php-webp
 * @author      Ryusuke SEKIYAMA <rsky0711@gmail.com>
 * @copyright   2011 Ryusuke SEKIYAMA
 * @license     http://www.opensource.org/licenses/mit-license.php  MIT License
 */

#include "php_webp.h"
#include "libwebp/src/webpimg.h"

#define MAX_IMAGE_SIDE_LENGTH 16384
#define DEFAULT_QP 20
#define MAX_QP 63
#define MIN_QP 0
#define CALC_QUALITY(qp) (long)(100.0 * (float)(MAX_QP - (qp)) / (float)MAX_QP)
#define CALC_QP(quality) (int)((float)MAX_QP * (1.0 - (float)(quality) / 100.0))

/* {{{ globals */

static long default_quality = -1;
static int le_gd = -1;
#ifdef GD_API_IS_HIDDEN
static int le_fake = -1;
static ZEND_DECLARE_MODULE_GLOBALS(webp);
#endif

/* }}} */
/* {{{ internal function prototypes */

static php_stream *
_pwp_stream_open(const char *filename, const char *mode,
                 int options, char **opened_path TSRMLS_DC);
#define pwp_file_open(filename, mode, opened_path) \
	_pwp_stream_open(filename, mode, IGNORE_URL, opened_path TSRMLS_CC)
#define pwp_url_open(filename, mode, opened_path) \
	_pwp_stream_open(filename, mode, 0, opened_path TSRMLS_CC)

#ifdef GD_API_IS_HIDDEN
static gdImagePtr
_pwp_gdImageCreateTrueColor(int sx, int sy);
#undef gdImageCreateTrueColor
#define gdImageCreateTrueColor(sx, sy) _pwp_gdImageCreateTrueColor(sx, sy)
#endif

/* }}} */
/* {{{ module function prototypes */

static PHP_MINIT_FUNCTION(webp);
#ifdef GD_API_IS_HIDDEN
static PHP_RINIT_FUNCTION(webp);
static PHP_RSHUTDOWN_FUNCTION(webp);
#endif
static PHP_MINFO_FUNCTION(webp);

/* }}} */
/* {{{ php function prototypes */

static PHP_FUNCTION(imagecreatefromwebp);
static PHP_FUNCTION(imagewebp);

/* }}} */
/* {{{ php function argument informations */

#if PHP_VERSION_ID < 50300
#define PHP_WEBP_BEGIN_ARG_INFO static ZEND_BEGIN_ARG_INFO_EX
#else
#define PHP_WEBP_BEGIN_ARG_INFO ZEND_BEGIN_ARG_INFO_EX
#endif

PHP_WEBP_BEGIN_ARG_INFO(arginfo_imagecreatefromwebp, ZEND_SEND_BY_VAL, ZEND_RETURN_VALUE, 1)
	ZEND_ARG_INFO(0, filename)
ZEND_END_ARG_INFO()

PHP_WEBP_BEGIN_ARG_INFO(arginfo_imagewebp, ZEND_SEND_BY_VAL, ZEND_RETURN_VALUE, 1)
	ZEND_ARG_INFO(0, image)
	ZEND_ARG_INFO(0, filename)
	ZEND_ARG_INFO(0, quality)
	ZEND_ARG_INFO(1, difference)
ZEND_END_ARG_INFO()

/* }}} */
/* {{{ webp_functions[] */

static zend_function_entry webp_functions[] = {
	PHP_FE(imagecreatefromwebp, arginfo_imagecreatefromwebp)
	PHP_FE(imagewebp,           arginfo_imagewebp)
	{ NULL, NULL, NULL }
};

/* }}} */
/* {{{ cross-extension dependencies */

static zend_module_dep webp_deps[] = {
	ZEND_MOD_REQUIRED("gd")
	{NULL, NULL, NULL, 0}
};

/* }}} */
/* {{{ webp_module_entry */

static zend_module_entry webp_module_entry = {
	STANDARD_MODULE_HEADER_EX,
	NULL,
	webp_deps,
	"webp",
	webp_functions,
	PHP_MINIT(webp),
	NULL,
#ifdef GD_API_IS_HIDDEN
	PHP_RINIT(webp),
	PHP_RSHUTDOWN(webp),
#else
	NULL,
	NULL,
#endif
	PHP_MINFO(webp),
	PHP_WEBP_VERSION,
	STANDARD_MODULE_PROPERTIES
};
/* }}} */

#ifdef COMPILE_DL_WEBP
ZEND_GET_MODULE(webp)
#endif

/* {{{ PHP_MINIT_FUNCTION */

static PHP_MINIT_FUNCTION(webp)
{
	le_gd = phpi_get_le_gd();
#ifdef GD_API_IS_HIDDEN
	le_fake = zend_register_list_destructors(NULL, NULL, module_number);
#endif

	default_quality = CALC_QUALITY(DEFAULT_QP);
	REGISTER_LONG_CONSTANT("WEBP_DEFAULT_QUALITY",
			default_quality, CONST_PERSISTENT | CONST_CS);

	return SUCCESS;
}

/* }}} */
#ifdef GD_API_IS_HIDDEN
/* {{{ PHP_RINIT_FUNCTION */

static PHP_RINIT_FUNCTION(webp)
{
	zval *ict_name;
	zend_fcall_info *fci;
	zend_fcall_info_cache *fcc;
	int result;

	MAKE_STD_ZVAL(ict_name);
	ZVAL_STRING(ict_name, "imagecreatetruecolor", 1);
	fci = &WEBPG(ict_fci);
	fcc = &WEBPG(ict_fcc);

#if ZEND_EXTENSION_API_NO >= 220090626
	result = zend_fcall_info_init(ict_name, 0, fci, fcc, NULL, NULL TSRMLS_CC);
#else
	result = zend_fcall_info_init(ict_name, fci, fcc TSRMLS_CC);
#endif

	if (FAILURE == result) {
		zval_ptr_dtor(&ict_name);
		return FAILURE;
	}
	WEBPG(ict_name) = ict_name;

	return SUCCESS;
}

/* }}} */
/* {{{ PHP_RSHUTDOWN_FUNCTION */

static PHP_RSHUTDOWN_FUNCTION(webp)
{
	zval_ptr_dtor(&WEBPG(ict_name));

	return SUCCESS;
}

/* }}} */
#endif
/* {{{ PHP_MINFO_FUNCTION */

static PHP_MINFO_FUNCTION(webp)
{
	php_info_print_table_start();
	php_info_print_table_row(2, "Version", PHP_WEBP_VERSION " (" PHP_WEBP_RELEASE ")");
	php_info_print_table_end();
}

/* }}} */
/* {{{ imagecreatefromwebp() */

/**
 * resource imagecreatefromwebp(string filename)
 * Create a new image from file or URL.
 */
static PHP_FUNCTION(imagecreatefromwebp)
{
	const char *filename = NULL;
	int filename_len = 0;
	php_stream *stream;
	char *data = NULL;
	size_t data_size;

	gdImagePtr im;
	uint32 *pix_buf;
	const uint32 *pix_ptr;
	uint8 *y_ptr = NULL, *u_ptr = NULL, *v_ptr = NULL;
	int x, y, width, height, words_per_line;
	WebPResult result;

	if (FAILURE == zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC,
			"s", &filename, &filename_len)
	) {
		return;
	}

	stream = pwp_file_open(filename, "rb", NULL);
	data_size = php_stream_copy_to_mem(stream, &data, PHP_STREAM_COPY_ALL, 0);
	if (!data_size) {
		if (data) {
			efree(data);
		}
		RETURN_FALSE;
	}

	result = WebPDecode((const uint8 *)data, (int)data_size,
			&y_ptr, &u_ptr, &v_ptr, &width, &height);
	if (result == webp_failure) {
		if (y_ptr) {
			free(y_ptr);
		}
		efree(data);
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to decode WebP image");
		RETURN_FALSE;
	}
	efree(data);

	words_per_line = width;
	pix_buf = (uint32 *)ecalloc((size_t)(width * height), sizeof(uint32));
	if (!pix_buf) {
		free(y_ptr);
		php_error(E_ERROR, "Failed to allocate memory");
		RETURN_FALSE;
	}

	YUV420toRGBA(y_ptr, u_ptr, v_ptr, words_per_line, width, height, pix_buf);
	free(y_ptr);

	im = gdImageCreateTrueColor(width, height);
	if (!im) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to create image");
		efree(pix_buf);
		RETURN_FALSE;
	}

	pix_ptr = pix_buf;
	for (y = 0; y < height; y++) {
		for (x = 0; x < width; x++) {
			im->tpixels[y][x] = (int)(*pix_ptr >> 8);
			pix_ptr++;
		}
	}

	ZEND_REGISTER_RESOURCE(return_value, im, le_gd);
	efree(pix_buf);
}

/* }}} */
/* {{{ imagewebp() */

/**
 * bool imagewebp(resource image [, string filename = NULL
 *     [, int quality = WEBP_DEFAULT_QUALITY [, float &difference = NULL] ]])
 * Output image to browser or file.
 */
static PHP_FUNCTION(imagewebp)
{
	zval *image = NULL;
	gdImagePtr im;
	const char *filename = NULL;
	int filename_len = 0;
	char *opened_path = NULL;
	php_stream *stream;
	size_t output_size;
	long quality = default_quality;
	int qp;
	zval *difference = NULL;

	int x, y, width, height, words_per_line;
	int uv_width, uv_height, uv_words_per_line;
	size_t y_nmemb, uv_nmemb;
	uint32 *pix_buf, *pix_ptr;
	uint8 *yuv_buf, *y_ptr, *u_ptr, *v_ptr;
	WebPResult result;
	unsigned char *out = NULL;
	int out_size_bytes = 0;
	double snr = 0.0;

	if (FAILURE == zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC,
			"r|s!lz", &image, &filename, &filename_len, &quality, &difference)
	) {
		return;
	}
	ZEND_FETCH_RESOURCE(im, gdImagePtr, &image, -1, "Image", le_gd);

	if (quality >= 100L) {
		qp = MIN_QP;
	} else if (quality <= 0L) {
		qp = MAX_QP;
	} else {
		qp = CALC_QP(quality);
	}

	width = gdImageSX(im);
	height = gdImageSY(im);
	if (width > MAX_IMAGE_SIDE_LENGTH || height > MAX_IMAGE_SIDE_LENGTH) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "The image size is too large");
		RETURN_FALSE;
	}

	uv_width = (width + 1) >> 1;
	uv_height = (height + 1) >> 1;
	y_nmemb = (size_t)(width * height);
	uv_nmemb = (size_t)(uv_width * uv_height);
	pix_buf = (uint32 *)ecalloc(y_nmemb, sizeof(uint32));
	yuv_buf = (uint8 *)ecalloc(y_nmemb + 2 * uv_nmemb, sizeof(uint8));
	if (pix_buf == NULL || yuv_buf == NULL) {
		if (yuv_buf) {
			efree(yuv_buf);
		}
		if (pix_buf) {
			efree(pix_buf);
		}
		php_error(E_ERROR, "Failed to allocate memory");
		RETURN_FALSE;
	}
	y_ptr = yuv_buf;
	u_ptr = y_ptr + y_nmemb;
	v_ptr = u_ptr + uv_nmemb;

	pix_ptr = pix_buf;
	if (gdImageTrueColor(im)) {
		for (y = 0; y < height; y++) {
			for (x = 0; x < width; x++) {
				*pix_ptr = (uint32)gdImageTrueColorPixel(im, x, y) << 8;
				pix_ptr++;
			}
		}
	} else {
		int c;
		for (y = 0; y < height; y++) {
			for (x = 0; x < width; x++) {
				c = gdImagePalettePixel(im, x, y);
				*pix_ptr = (((uint32)im->red[c]) << 24)
						| (((uint32)im->green[c]) << 16)
						| (((uint32)im->blue[c]) << 8);
				pix_ptr++;
			}
		}
	}

	words_per_line = width;
	uv_words_per_line = uv_width;
	RGBAToYUV420(pix_buf, words_per_line, width, height, y_ptr, u_ptr, v_ptr);
	result = WebPEncode(y_ptr, u_ptr, v_ptr,
			width, height, words_per_line,
			uv_width, uv_height, uv_words_per_line,
			qp, &out, &out_size_bytes, &snr);

	efree(yuv_buf);
	efree(pix_buf);

	if (result == webp_failure) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING, "Failed to encode WebP image");
		RETURN_FALSE;
	}

	if (difference) {
		zval_dtor(difference);
		ZVAL_DOUBLE(difference, snr);
	}

	if (filename) {
		stream = pwp_file_open(filename, "wb", &opened_path);
	} else {
		stream = pwp_url_open("php://output", "wb", NULL);
	}
	if (stream) {
		output_size = php_stream_write(stream, (const char *)out, (size_t)out_size_bytes);
		if (output_size != (size_t)out_size_bytes) {
			if (opened_path) {
				php_error_docref(NULL TSRMLS_CC, E_WARNING,
						"failed to write data to %s", opened_path);
			} else {
				php_error_docref(NULL TSRMLS_CC, E_WARNING,
						"failed to write data");
			}
			RETVAL_FALSE;
		} else {
			RETVAL_TRUE;
		}
	} else {
		RETVAL_FALSE;
	}
	if (opened_path) {
		efree(opened_path);
	}
	free(out);
}

/* }}} */
/* {{{ _pwp_stream_open() */

static php_stream *
_pwp_stream_open(const char *filename, const char *mode,
                 int options, char **opened_path TSRMLS_DC)
{
	php_stream *stream;
	char *path = NULL;

	stream = php_stream_open_wrapper((char *)filename, (char *)mode,
			ENFORCE_SAFE_MODE | REPORT_ERRORS | options, &path);

	if (stream == NULL) {
		php_error_docref(NULL TSRMLS_CC, E_WARNING,
				"Argument 1 should be a valid filename");
		return NULL;
	}

	if (opened_path != NULL) {
		*opened_path = path;
	} else {
		efree(path);
	}

	return stream;
}

/* }}} */
#ifdef GD_API_IS_HIDDEN
/* {{{ _pwp_gdImageCreateTrueColor() */

static gdImagePtr
_pwp_gdImageCreateTrueColor(int sx, int sy)
{
	TSRMLS_FETCH();
	gdImagePtr im = NULL;
	zval *zim = NULL, *args;

	MAKE_STD_ZVAL(args);
	array_init(args);
	add_next_index_long(args, (long)sx);
	add_next_index_long(args, (long)sy);

	zend_fcall_info_call(&WEBPG(ict_fci), &WEBPG(ict_fcc), &zim, args TSRMLS_CC);
	if (zim) {
		if (Z_TYPE_P(zim) == IS_RESOURCE) {
			zend_rsrc_list_entry *le;

			ZEND_FETCH_RESOURCE_NO_RETURN(im, gdImagePtr, &zim, -1, "Image", le_gd);
			if (SUCCESS == zend_hash_index_find(&EG(regular_list),
					(ulong)Z_LVAL_P(zim), (void **)&le)
			) {
				le->ptr = NULL;
				le->type = le_fake;
			}
		}
		zval_ptr_dtor(&zim);
	}
	zval_ptr_dtor(&args);

	return im;
}

/* }}} */
#endif

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
