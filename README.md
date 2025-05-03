The Operatable Tensor Object
============================
Status:
[![Build Status](https://github.com/rindow/rindow-math-tensor/workflows/tests/badge.svg)](https://github.com/rindow/rindow-math-tensor/actions)
[![Downloads](https://img.shields.io/packagist/dt/rindow/rindow-math-tensor)](https://packagist.org/packages/rindow/rindow-math-tensor)
[![Latest Stable Version](https://img.shields.io/packagist/v/rindow/rindow-math-tensor)](https://packagist.org/packages/rindow/rindow-math-tensor)
[![License](https://img.shields.io/packagist/l/rindow/rindow-math-tensor)](https://packagist.org/packages/rindow/rindow-math-tensor)

Rindow Math Tensor a operatable tensor object for multidimensional arrays

- A powerful N-dimensional array object


Please see the documents on [Rindow mathematics project](https://rindow.github.io/mathematics/) web pages.

Requirements
============

- PHP 8.1 or PHP8.2 or PHP8.3 or PHP8.4
- Rindow-Operatorovr PHP extension v0.1 or later
- Rindow-Math-Matrix v2.1 or later

### Strong recommend ###
You can perform very fast N-dimensional array operations in conjunction

- [rindow-math-matrix-matlibffi](https://github.com/rindow/rindow-math-matrix-matlibffi): plug-in drivers for OpenBLAS,Rindow-Matlib,OpenCL,CLBlast for FFI
- Pre-build binaries
  - [Rindow matlib](https://github.com/rindow/rindow-matlib/releases)
  - [OpenBLAS](https://github.com/OpenMathLib/OpenBLAS/releases)
  - [CLBlast](https://github.com/CNugteren/CLBlast/releases)

Please see the [rindow-math-matrix-matlibffi](https://github.com/rindow/rindow-math-matrix-matlibffi) to setup plug-in and pre-build binaries.

How to Setup
============
### Download pre-build binaries
Download pre-build binaries from [rindow-operatorovr](https://github.com/rindow/rindow-operatorovr/releases).

### Install PHP the extension for Windows/macOS
- Extract Zip file, and Copy dll/so file to PHP Extension directory.
- Add the "extension=php_rindow_opoverride" entry to php.ini

### Install PHP the extension for Ubuntu
- Install deb file.
```shell
$ sudo apt install ./rindow-operatorovr-XXX-XXX.deb
```

### Install PHP Modules
Set it up using composer.

```shell
$ composer require rindow/rindow-math-tensor
```

You can use it as is, but you will need to speed it up to process at a practical speed.

And then, Set up pre-build binaries for the required high-speed calculation libraries. Click [here](https://github.com/rindow/rindow-math-matrix-matlibffi) for details.

```shell
$ composer require rindow/rindow-math-matrix-matlibffi
```

Sample programs
===============
```php
<?php
// sample.php
include __DIR__.'/vendor/autoload.php';
$tf = Rindow\Math\Tensor\Factory::new();
$a = $tf->Tensor([[1,2],[3,4]]);
$b = $tf->Tensor([[2,3],[4,5]]);
$c = $a**$b;
$tf->println($c);
```

```shell
$ php sample.php
[
 [10,13],
 [22,29]
]
```
