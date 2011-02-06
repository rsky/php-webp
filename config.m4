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
#error this extension requires at least PHP version 5.2.0
#endif
],
[AC_MSG_RESULT(ok)],
[AC_MSG_ERROR([need at least PHP 5.2.0])])

  dnl
  dnl Check the libvpx support
  dnl
  WEBP_VPX_DIR=""
  if test "$PHP_WEBP_VPX_DIR" != "yes"; then
    AC_MSG_CHECKING([for vpx/vp8.h])
    if test -r "$PHP_WEBP_VPX_DIR/include/vpx/vp8.h"; then
      WEBP_VPX_DIR="$PHP_WEBP_VPX_DIR"
      AC_MSG_RESULT([yes])
    fi
  else
    AC_MSG_CHECKING([for vpx/vp8.h in default path])
    for i in /usr /usr/local; do
      if test -r "$i/include/vpx/vp8.h"; then
        WEBP_VPX_DIR=$i
        AC_MSG_RESULT([found in $i])
        break
      fi
    done
  fi
  if test "x" = "x$WEBP_VPX_DIR"; then
    AC_MSG_ERROR([not found])
  fi

  PHP_ADD_INCLUDE($WEBP_VPX_DIR/include)
  PHP_ADD_LIBRARY_WITH_PATH(vpx, $WEBP_VPX_DIR/lib, WEBP_SHARED_LIBADD)

  export CPPFLAGS="$OLD_CPPFLAGS"

  PHP_ADD_INCLUDE(./libwebp/src)
  PHP_SUBST(WEBP_SHARED_LIBADD)
  AC_DEFINE(HAVE_WEBP, 1, [ ])

  PHP_NEW_EXTENSION(webp, webp.c libwebp/src/webpimg.c, $ext_shared)
fi
