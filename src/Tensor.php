<?php
namespace Rindow\Math\Tensor;

use Interop\Polite\Math\Matrix\NDArray;
use Interop\Polite\Math\Matrix\Buffer;
use Rindow\OperatorOvl\Operatable;
use InvalidArgumentException;
use Countable;
use IteratorAggregate;
use Traversable;

// - ADD(+)            __add
// - SUB(-)            __sub
// - MUL(*)            __mul
// - DIV(/)            __div
// - MOD(%),           __mod
// - POW(**),          __pow
// - SL(<<),           __sl
// - SR(>>),           __sr
// - CONCAT(.),        __concat
// - BW_OR(|),         __bw_or
// - BW_AND(&),        __bw_and
// - BW_XOR(^),        __bw_xor
// - BW_NOT(~),        __bw_not
// - BOOL_XOR(xor),    __bool_xor


/**
 * @implements IteratorAggregate<int, mixed>
 */
class Tensor extends Operatable implements NDArray, Countable, IteratorAggregate
{
    protected object $mo;
	protected NDArray $value;

	public function __construct(object $mo, mixed $value, ?int $dtype=null)
	{
        $this->mo = $mo;
        if($value instanceof static) {
            $value = $value->value();
        } elseif(!($value instanceof NDArray)) {
            $value = $this->mo->array($value, dtype:$dtype);
        }
        $this->value = $value;
	}

    /**
     * @return array<int>
     */
    public function shape() : array
    {
        return $this->value->shape();
    }

    public function ndim() : int
    {
        return $this->value->ndim();
    }

    public function dtype() : int
    {
        return $this->value->dtype();
    }

    public function buffer() : Buffer
    {
        return $this->value->buffer();
    }

    public function offset() : int
    {
        return $this->value->offset();
    }

    public function size() : int
    {
        return $this->value->size();
    }

    /**
     * @param array<int> $shape
     */
    public function reshape(array $shape) : NDArray
    {
        return $this->value->reshape($shape);
    }

    public function toArray() : mixed
    {
        return $this->value->toArray();
    }

    public function offsetExists($offset) : bool
    {
        return $this->value->offsetExists($offset);
    }

    public function offsetGet($offset) : mixed
    {
        if($this->value->ndim()==1 && is_numeric($offset)) {
            $value = $this->value->offsetGet([$offset,$offset+1]);
            $value = $this->mo->la()->squeeze($value,axis:0);
        } else {
            $value = $this->value->offsetGet($offset);
        }
        return new self($this->mo,$value);
    }

    public function offsetSet($offset, $value) : void
    {
        if($value instanceof static) {
            $value = $value->value();
        }
        $this->value->offsetSet($offset, $value);
    }

    public function offsetUnset($offset) : void
    {
        $this->value->offsetUnset($offset);
    }

    public function count() : int
    {
        return $this->value->count();
    }

    public function getIterator() : Traversable
    {
        if($this->value->ndim()==0) {
            return [];
        }
        $count = $this->value->count();
        for($i=0;$i<$count;$i++) {
            yield $i => $this->offsetGet($i);
        }
    }


    /**
     *  - ADD(+)            __add
     */
    public function __add(mixed $value) : self
	{
        $la = $this->mo->la();
        if(is_numeric($value)) {
            $newvalue = $la->increment($la->copy($this->value),$value);
        } elseif($value instanceof static) {
            $newvalue = $la->add($value->value(),$la->copy($this->value));
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}

    /**
     *  - SUB(-)            __sub
     */
	public function __sub(mixed $value) : self
	{
        $la = $this->mo->la();
        if(is_numeric($value)) {
            $newvalue = $la->increment($la->copy($this->value),-$value);
        } elseif($value instanceof static) {
            $newvalue = $la->add($value->value(),$la->copy($this->value),alpha:-1);
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}

    /**
     *  - MUL(*)            __mul
     */
	public function __mul(mixed $value) : self
	{
        $la = $this->mo->la();
        if(is_numeric($value)) {
            $newvalue = $la->scal($value,$la->copy($this->value));
        } elseif($value instanceof static) {
            $newvalue = $la->multiply($value->value(),$la->copy($this->value));
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}

    /**
     *  - DIV(/)            __div
     */
	public function __div(mixed $value) : self
	{
        $la = $this->mo->la();
        if(is_numeric($value)) {
            $newvalue = $la->scal(1/$value,$la->copy($this->value));
        } elseif($value instanceof static) {
            $rvalue = $la->reciprocal($la->copy($value->value()));
            if($rvalue->shape()==$this->value->shape()) {
                $newvalue = $la->multiply($this->value,$rvalue);
            } else {
                $newvalue = $la->multiply($rvalue,$la->copy($this->value));
            }
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}

    /**
     *  - POW(**),          __pow
     */
	public function __pow(mixed $value) : self
	{
        $la = $this->mo->la();
        if($value instanceof static) {
            if($this->value->ndim() > $value->value()->ndim()) {
                $newvalue = $la->gemv($this->value,$la->copy($value->value()));
            } else {
                $newvalue = $la->matmul($this->value,$value->value());
            }
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}

    /**
     *  - BW_XOR(^),        __bw_xor
     */
	public function __bw_xor(mixed $value) : self
	{
        $la = $this->mo->la();
        if(is_string($value)) {
            if($value=='T') {
                $newvalue = $la->transpose($this->value);
            } else {
                throw new InvalidArgumentException('value must be T');
            }
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}

    /**
     *  - MOD(%),           __mod
     */
	public function __mod(mixed $value) : string
	{
        $la = $this->mo->la();
        if(is_string($value)) {
            if($value=='T') {
                $newvalue = $la->transpose($this->value);
            } else {
                throw new InvalidArgumentException('value must be T');
            }
        } else {
            throw new InvalidArgumentException('unknown value type');
        }
        return new self($this->mo,$newvalue);
	}



    // - MOD(%),           __mod
    // - SL(<<),           __sl
    // - SR(>>),           __sr
    // - CONCAT(.),        __concat
    // - BW_OR(|),         __bw_or
    // - BW_AND(&),        __bw_and
    // - BW_NOT(~),        __bw_not
    // - BOOL_XOR(xor),    __bool_xor

	public function __toString() : string
	{
        $string = $this->mo->toString($this->value);
        return $string;
	}

	public function value() : NDArray
	{
		return $this->value;
	}


}
