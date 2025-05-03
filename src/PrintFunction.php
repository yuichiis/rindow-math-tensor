<?php
namespace Rindow\Math\Tensor;

use Rindow\Math\Matrix\MatrixOperator;
use Interop\Polite\Math\Matrix\NDArray;

trait PrintFunction
{
    public function println(mixed ...$values) : void
    {
        $mo = Factory::defaultMo();
        $first = true;
        foreach($values as $value) {
            if(!$first) {
                echo ' ';
            }
            $first = false;
            if($value instanceof NDArray) {
                echo $mo->toString($value,indent:true);
            } else {
                echo strval($value);
            }
        }
        echo "\n";
    }

    public function printfln(string $format, mixed ...$values) : void
    {
        $mo = Factory::defaultMo();
        $first = true;
        foreach($values as $value) {
            if(!$first) {
                echo ' ';
            }
            $first = false;
            if($value instanceof NDArray) {
                echo $mo->toString($value,format:$format,indent:true);
            } elseif(is_numeric($value)) {
                echo sprintf($format,$value);
            } else {
                echo strval($value);
            }
        }
        echo "\n";
    }
}