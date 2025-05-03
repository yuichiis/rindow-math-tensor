<?php
namespace Rindow\Math\Tensor;

use Rindow\OperatorOvl\Operatable;

class CoutAttribute
{
    public function __construct(
        public string $name,
        public mixed $value=null,
    )
    {}
}
