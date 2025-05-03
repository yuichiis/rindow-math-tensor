<?php
namespace Rindow\Math\Tensor;

use Rindow\OperatorOvl\Operatable;
use Interop\Polite\Math\Matrix\NDArray;
use InvalidArgumentException;

class Cout extends Operatable
{
    public string $endl = "\n";

    /** @var array<int,bool> $intDtypes */
    protected static array $intDtypes = [
        NDArray::int8 => true,
        NDArray::uint8 => true,
        NDArray::int16 => true,
        NDArray::uint16 => true,
        NDArray::int32 => true,
        NDArray::uint32 => true,
        NDArray::int64 => true,
        NDArray::uint64 => true,
    ];

    protected object $mo;
    protected bool $fixed = false;
    protected ?int $precision = null;
    protected bool $scientific = false;
    protected ?int $width = null;
    protected bool $left = false;
    protected ?string $fill = null;

    public function __construct(object $mo)
    {
        $this->mo = $mo;
    }

    public function __sl(mixed $value) : static
    {
        if($value instanceof CoutAttribute) {
            $this->setAttribute($value);
            return $this;
        }
        $format = null;
        $widthFormat = '';
        $precisionFormat = '';
        $precision = $this->precision;
        if($this->width!==null) {
            if($this->fill===null) {
                $fill = '';
            } else {
                $fill = $this->fill;
            }
            $widthFormat = ($this->left?'-':'').$fill.$this->width;
        }
        if($this->fixed && $precision===null) {
            $precision = 2;
        }
        if($precision!==null) {
            $precisionFormat = ".{$this->precision}";
        }
        if($this->fixed || $this->precision!==null) {
            $precision = $this->precision ?? 2;
            $format = "%".$widthFormat.$precisionFormat.($this->fixed?'f':'g');
        } elseif($this->scientific) {
            $format = "%".$widthFormat.$precisionFormat."e";
        } elseif($this->width!==null) {
            $format = "%".$widthFormat.($this->isIntType($value)?'d':'f');
        }
        if($format!==null) {
            if(is_numeric($value)) {
                echo sprintf($format,$value);
            } elseif(is_string($value)) {
                echo strval($value);
            } elseif($value instanceof NDArray) {
                if($value instanceof Tensor) {
                    $value = $value->value();
                }
                echo $this->mo->toString($value,format:$format,indent:true);
            } else {
                throw new InvalidArgumentException('unknown value type');
            }
            return $this;
        }
        echo strval($value);
        return $this;
    }

    protected function isIntType(mixed $value) : bool
    {
        if(is_numeric($value)) {
            return is_int($value);
        } elseif($value instanceof NDArray) {
            return array_key_exists($value->dtype(), static::$intDtypes);
        }
        return false;
    }

    protected function setAttribute(CoutAttribute $attr) : void
    {
        switch($attr->name) {
            case 'fixed':
                $this->fixed = true;
                break;
            case 'precision':
                $this->precision = $attr->value;
                break;
            case 'scientific':
                $this->scientific = true;
                break;
            case 'width':
                $this->width = $attr->value;
                break;
            case 'left':
                $this->left = true;
                break;
            case 'fill':
                $this->fill = $attr->value;
                break;
            default:
                throw new InvalidArgumentException('unknown attribute');
        }
    }

    public function endl() : string
    {
        return $this->endl;
    }

    public function fixed() : CoutAttribute
    {
        return new CoutAttribute('fixed');
    }

    public function setprecision(int $precision) : CoutAttribute
    {
        return new CoutAttribute('precision',$precision);
    }

    public function scientific() : CoutAttribute
    {
        return new CoutAttribute('scientific');
    }

    public function setw(int $width) : CoutAttribute
    {
        return new CoutAttribute('width',$width);
    }

    public function left() : CoutAttribute
    {
        return new CoutAttribute('left');
    }

    public function right() : CoutAttribute
    {
        return new CoutAttribute('right');
    }

    public function setfill(string $fill) : CoutAttribute
    {
        return new CoutAttribute('fill',$fill);
    }

}
