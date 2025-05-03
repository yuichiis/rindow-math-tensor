<?php
namespace RindowTest\Math\Tensor\TensorTest;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Interop\Polite\Math\Matrix\NDArray;
use Rindow\Math\Tensor\Tensor;

class TensorTest extends TestCase
{
    public function testNormal()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor(2);
        $arr2 = $tf->Tensor(3);
        
        $this->assertEquals('5',strval($arr1+$arr2));
        $this->assertEquals('3',strval($arr1+1));
        $this->assertEquals('6',strval($arr1*$arr2));
        $this->assertEquals('4',strval($arr1*2));
        
        $arr1 = $tf->Tensor([1,2]);
        $arr2 = $tf->Tensor([2,4]);
        
        $this->assertEquals('[3,6]',strval($arr1+$arr2));
        $this->assertEquals('[2,3]',strval($arr1+1));
        $this->assertEquals('[2,8]',strval($arr1*$arr2));
        $this->assertEquals('[4,8]',strval($arr2*2));
        
        $arr1 = $tf->Tensor([1,1]);
        $arr2 = $tf->Tensor([2,2]);
        $arr3 = $tf->Tensor([3,3]);
        
        $this->assertEquals('[4,4]',strval( -$arr1*$arr2 + $arr3*2 ));  // (-1*2)+(3*2)=4
        
        $backup = $arr1;
        $this->assertEquals(spl_object_id($arr1),spl_object_id($backup));
        $this->assertEquals('[1,1]',strval($arr1++));
        $this->assertEquals('[2,2]',strval($arr1));
        $this->assertEquals('[1,1]',strval($backup));                     // **** CAUTION ****
        $this->assertNotEquals(spl_object_id($arr1),spl_object_id($backup)); // **** CAUTION ****

    }

    public function testAsign()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $ndarray = $tf->mo()->array([1,2]);
        $arr1 = $tf->Tensor($ndarray);
        $this->assertEquals(spl_object_id($ndarray),spl_object_id($arr1->value()));

        $tensor = $tf->Tensor([1,2]);
        $arr1 = $tf->Tensor($tensor);
        $this->assertEquals(spl_object_id($tensor->value()),spl_object_id($arr1->value()));

    }

    public function testDtype()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([1,2],dtype:NDArray::int32);
        $this->assertEquals(NDArray::int32,$arr1->dtype());

        $arr1 = $tf->Tensor([1,2],dtype:NDArray::float32);
        $this->assertEquals(NDArray::float32,$arr1->dtype());
    }

    public function testArrayAccess()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        // 1D
        $arr1 = $tf->Tensor([1,2]);

        $this->assertCount(2,$arr1);
        $this->assertEquals('1',strval($arr1[0]));
        $this->assertEquals('2',strval($arr1[1]));
        $this->assertInstanceof(Tensor::class,$arr1[0]);
        $this->assertInstanceof(Tensor::class,$arr1[1]);
        $this->assertEquals('[1]',strval($arr1[[0,1]]));
        $this->assertInstanceof(Tensor::class,$arr1[[0,1]]);

        $arr1[0] = 3;
        $this->assertEquals('3',strval($arr1[0]));
        $this->assertEquals('2',strval($arr1[1]));

        // 2D
        $arr1 = $tf->Tensor([[1,2],[3,4]]);

        $this->assertCount(2,$arr1);
        $this->assertEquals('[1,2]',strval($arr1[0]));
        $this->assertEquals('[3,4]',strval($arr1[1]));
        $this->assertInstanceof(Tensor::class,$arr1[0]);
        $this->assertInstanceof(Tensor::class,$arr1[1]);
        $this->assertEquals('[[1,2]]',strval($arr1[[0,1]]));
        $this->assertInstanceof(Tensor::class,$arr1[[0,1]]);

        $arr1[0] = $tf->Tensor([2,3]);;
        $this->assertEquals('[2,3]',strval($arr1[0]));
        $this->assertEquals('[3,4]',strval($arr1[1]));
    }

    public function testIterator()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        // 1D
        $arr1 = $tf->Tensor([1,2]);

        $count = 0;
        foreach($arr1 as $i => $v) {
            $this->assertInstanceof(Tensor::class,$v);
            if($i==0) {
                $this->assertEquals('1',strval($v));
            } else {
                $this->assertEquals('2',strval($v));
            }
            $count++;
        }
        $this->assertEquals(2,$count);

        // 2D
        $arr1 = $tf->Tensor([[1,2],[3,4]]);

        $count = 0;
        foreach($arr1 as $i => $v) {
            $this->assertInstanceof(Tensor::class,$v);
            if($i==0) {
                $this->assertEquals('[1,2]',strval($v));
            } else {
                $this->assertEquals('[3,4]',strval($v));
            }
            $count++;
        }
        $this->assertEquals(2,$count);
        
    }

    public function testAsNDArray()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([[1,2],[3,4]]);

        // Linear Algebra
        $tf->la()->scal(2,$arr1);
    
        // Matrix Operator
        $this->assertEquals(
            "[\n".
            " [2.0,4.0],\n".
            " [6.0,8.0]\n".
            "]",
            $tf->mo()->toString($arr1,format:'%3.1f',indent:true)
        );
    }

    public function testShape()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([1,2],dtype:NDArray::int32);
        $this->assertEquals([2],$arr1->shape());

        $arr2 = $arr1->reshape([2,1]);
        $this->assertEquals([2,1],$arr2->shape());
    }

    public function testAdd()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([1,2]);
        $arr2 = $tf->Tensor([2,4]);
        
        $this->assertEquals('[3,6]',strval($arr1+$arr2));
        $this->assertEquals('[2,3]',strval($arr1+1));
    }

    public function testMul()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([1,2]);
        $arr2 = $tf->Tensor([2,4]);
        
        $this->assertEquals('[2,8]',strval($arr1*$arr2));
        $this->assertEquals('[4,8]',strval($arr2*2));
    }
    
    public function testDiv()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([2,4]);
        $arr2 = $tf->Tensor([1,2]);
        
        $this->assertEquals('[2,2]',strval($arr1/$arr2));
        $this->assertEquals('[1,2]',strval($arr1/2));
    }
    
    public function testCrossProduct()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([[1,2],[3,4]]);
        $arr2 = $tf->Tensor([[5,6],[7,8]]);
        $arr3 = $tf->Tensor([5,6]);
        
        $this->assertEquals('[[19,22],[43,50]]',strval($arr1**$arr2));
        $this->assertEquals('[17,39]',strval($arr1**$arr3));
    }
    
    public function testTranspose()
    {
        $tf = \Rindow\Math\Tensor\Factory::new();

        $arr1 = $tf->Tensor([[1,2],[3,4]]);
        
        $this->assertEquals('[[1,3],[2,4]]',strval($arr1^'T'));
    }
    

}
