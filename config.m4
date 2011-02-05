PHP_ARG_ENABLE(webp, [whether to enable WebP support],
[  --enable-webp         Enable WebP support], yes, yes)

PHP_ARG_WITH(webp-vpx-dir, [libvpx installation prefix],
[  --with-webp-vpx-dir   libvpx installation prefix], yes, no)

if test "$PHP_WEBP" != "no"; then
  export OLD_CPPFLAGS="$CPPFLAGS"
  export CPPFLAGS="$CPPFLAGS $INCLUDES -DHAVE_WEBP"

  AC_MSG_CHECKING(PHP version)
  AC_TRY_COMPILE([#include <php_version.h>], [
#if !defined(PHP_VERSION_ID) || PHP_VERSION_ID < 50200
#error this extension requires at least PHP version 5.1.0rc1
#endif
],
[AC_MSG_RESULT(ok)],
[AC_MSG_ERROR([need at least PHP 5.2.0])])

  dnl
  dnl Check the zlib support
  dnl
  if test "$PHP_WEBP_VPX_DIR" != "yes"; then
    if test -r "$PHP_WEBP_VPX_DIR/include/vpx/vp8.h"; then
      PHP_WEBP_VPX_DIR="$PHP_WEBP_VPX_DIR"
    fi
  else
    AC_MSG_CHECKING([for webp-vpx-dir in default path])
    for i in /usr /usr/local; do
      if test -r "$i/include/vpx/vp8.h"; then
        PHP_WEBP_VPX_DIR=$i
        AC_MSG_RESULT([found in $i])
        break
      fi
    done
    if test "x" = "x$PHP_WEBP_VPX_DIR"; then
      AC_MSG_ERROR([not found])
    fi
  fi

  PHP_ADD_INCLUDE($PHP_WEBP_VPX_DIR/include)
  PHP_ADD_LIBRARY_WITH_PATH(vpx, $PHP_WEBP_VPX_DIR/lib, WEBP_SHARED_LIBADD)

  export CPPFLAGS="$OLD_CPPFLAGS"

  PHP_ADD_INCLUDE(./libwebp/src)
  PHP_SUBST(WEBP_SHARED_LIBADD)
  AC_DEFINE(HAVE_WEBP, 1, [ ])

  PHP_NEW_EXTENSION(webp, webp.c libwebp/src/webpimg.c, $ext_shared)

fi
