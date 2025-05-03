<?php
namespace Rindow\Math\Tensor;

use Rindow\Math\Matrix\MatrixOperator;
use Interop\Polite\Math\Matrix\NDArray;

class Factory
{
    use CoutFunction;
    use PrintFunction;

    static private ?object $defaultMo=null;
    private object $mo;

    static public function defaultMo() : object
    {
        if(self::$defaultMo==null) {
            self::$defaultMo = new MatrixOperator();
        }
        return self::$defaultMo;
    }

    static public function new(?object $mo=null) : self
    {
        return new self($mo);
    }

    public function __construct(?object $mo=null)
    {
        if($mo===null) {
            $mo = self::defaultMo();
        }
        $this->mo = $mo;
    }

    public function mo() : object
    {
        return $this->mo;
    }

    public function la() : object
    {
        return $this->mo->la();
    }

    public function Tensor(mixed $value, ?int $dtype=null) : Tensor
    {
        return new Tensor($this->mo,$value,dtype:$dtype);
    }

}

