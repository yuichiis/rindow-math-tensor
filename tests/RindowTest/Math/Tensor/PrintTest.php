<?php
namespace RindowTest\Math\Tensor\PrintTest;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Interop\Polite\Math\Matrix\NDArray;
use Rindow\Math\Tensor\Tensor;

class PrintTest extends TestCase
{
    public function testNormal()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $x = $tf->Tensor([2]);
        $y = $tf->Tensor([3]);
        
        ob_start();
        $tf->println("x=$x, y=$y");
        $output = ob_get_clean();
        $this->assertEquals("x=[2], y=[3]\n", $output);

        ob_start();
        $tf->println($x,$y);
        $output = ob_get_clean();
        $this->assertEquals("[2] [3]\n", $output);

        ob_start();
        $tf->printfln("%5.3f",$x,$y);
        $output = ob_get_clean();
        $this->assertEquals("[2.000] [3.000]\n", $output);


        ob_start();
        $tf->printfln("%5.3f",2,3);
        $output = ob_get_clean();
        $this->assertEquals("2.000 3.000\n", $output);
    }
}
