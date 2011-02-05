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

#ifndef PHP_WEBP_H
#define PHP_WEBP_H

#ifdef  __cplusplus
extern "C" {
#endif

#ifdef HAVE_CONFIG_H
#include "config.h"
#endif

#define PHP_WEBP_VERSION "0.0.5"
#define PHP_WEBP_RELEASE "alpha"

#include <php.h>
#include <ext/standard/info.h>
#include <Zend/zend_extensions.h>
#ifdef HAVE_GD_BUNDLED
#include <ext/gd/libgd/gd.h>
#include <ext/gd/libgd/gdhelpers.h>
#else
#include <gd.h>
#endif

#if PHP_VERSION_ID >= 50300
#define PHP_WEBP_USE_GD_WREPPER

ZEND_BEGIN_MODULE_GLOBALS(webp)
	zval *ict_name;
	zend_fcall_info ict_fci;
	zend_fcall_info_cache ict_fcc;
ZEND_END_MODULE_GLOBALS(webp)

#ifdef ZTS
#define WEBPG(v) TSRMG(webp_globals_id, zend_webp_globals *, v)
#else
#define WEBPG(v) (webp_globals.v)
#endif
#endif

#ifdef  __cplusplus
} // extern "C"
#endif

#endif /* PHP_WEBP_H */


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
