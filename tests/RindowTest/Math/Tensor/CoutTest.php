<?php
namespace RindowTest\Math\Tensor\CoutTest;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use Rindow\Math\Tensor\Factory as Cout;
use Rindow\Math\Tensor\Factory as TensorFactory;
use Interop\Polite\Math\Matrix\NDArray;

class CoutTest extends TestCase
{
    public function testNormal()
    {
        $tf = TensorFactory::new();

        $arr1 = $tf->Tensor([1,2]);
        $arr2 = $tf->Tensor([2,4]);

        ob_start();
        Cout::cout() << $arr1 << ":" << $arr2 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[1,2]:[2,4]\n", $output);
    }

    public function testFormatNDArray()
    {
        $tf = TensorFactory::new();

        // Fixed
        $arr1 = $tf->Tensor([1234.125,2345.125]);
        ob_start();
        Cout::cout() << Cout::fixed() << Cout::setprecision(3) << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[1234.125,2345.125]\n", $output);

        // Precision
        $arr1 = $tf->Tensor([1234.125,2345.125]);
        ob_start();
        Cout::cout() << Cout::setprecision(3) << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[1.23e+3,2.35e+3]\n", $output);

        // Scientific
        $arr1 = $tf->Tensor([1234.125,2345.125]);
        ob_start();
        Cout::cout() << Cout::scientific() << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[1.234125e+3,2.345125e+3]\n", $output);

        // Width on Float
        $arr1 = $tf->Tensor([1.5,1.5],dtype:NDArray::float32);
        ob_start();
        Cout::cout() << Cout::setw(10) << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[  1.500000,  1.500000]\n", $output);

        // Width on Integer
        $arr1 = $tf->Tensor([1,2],dtype:NDArray::int32);
        ob_start();
        Cout::cout() << Cout::setw(10) << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[         1,         2]\n", $output);

        // Left
        $arr1 = $tf->Tensor([1,2],dtype:NDArray::int32);
        ob_start();
        Cout::cout() << Cout::setw(10) << Cout::left() << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[1         ,2         ]\n", $output);

        // Fill
        $arr1 = $tf->Tensor([1,2],dtype:NDArray::int32);
        ob_start();
        Cout::cout() << Cout::setw(10) << Cout::setfill('0') << $arr1 << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("[0000000001,0000000002]\n", $output);

    }

    public function testFormatScalar()
    {
        $tf = TensorFactory::new();

        // Fixed
        $value = 1234.125;
        ob_start();
        Cout::cout() << Cout::fixed() << Cout::setprecision(3) << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("1234.125\n", $output);

        // Precision
        $value = 1234.125;
        ob_start();
        Cout::cout() << Cout::setprecision(3) << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("1.23e+3\n", $output);

        // Scientific
        $value = 1234.125;
        ob_start();
        Cout::cout() << Cout::scientific() << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("1.234125e+3\n", $output);

        // Width on Float
        $value = 1.5;
        ob_start();
        Cout::cout() << Cout::setw(10) << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("  1.500000\n", $output);

        // Width on Integer
        $value = 5;
        ob_start();
        Cout::cout() << Cout::setw(10) << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("         5\n", $output);

        // Left
        $value = 5;
        ob_start();
        Cout::cout() << Cout::setw(10) << Cout::left() << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("5         \n", $output);

        // Fill
        $value = 5;
        ob_start();
        Cout::cout() << Cout::setw(10) << Cout::setfill('0') << $value << Cout::endl();
        $output = ob_get_clean();
        $this->assertEquals("0000000005\n", $output);

    }
}
