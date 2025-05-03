<?php
namespace Rindow\Math\Tensor;

trait CoutFunction
{
    static public function cout() : Cout
    {
        $mo = Factory::defaultMo();
        return new Cout($mo);
    }

    static public function endl() : string
    {
        return "\n";
    }

    static public function fixed() : CoutAttribute
    {
        return new CoutAttribute('fixed');
    }

    static public function setprecision(int $precision) : CoutAttribute
    {
        return new CoutAttribute('precision',$precision);
    }

    static public function scientific() : CoutAttribute
    {
        return new CoutAttribute('scientific');
    }

    static public function setw(int $width) : CoutAttribute
    {
        return new CoutAttribute('width',$width);
    }

    static public function left() : CoutAttribute
    {
        return new CoutAttribute('left');
    }

    static public function right() : CoutAttribute
    {
        return new CoutAttribute('right');
    }

    static public function setfill(string $fill) : CoutAttribute
    {
        return new CoutAttribute('fill',$fill);
    }

}

