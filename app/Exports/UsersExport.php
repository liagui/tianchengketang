<?php

/*namespace App\Exports;

use App\Models\Chapters;
use Maatwebsite\Excel\Concerns\FromCollection;

class UsersExport implements FromCollection
{
    public function collection()
    {
        return Chapters::all();
    }
}*/


namespace App\Exports;
use App\Models\Student;
//use Maatwebsite\Excel\Concerns\FromArray;
//class UsersExport implements FromArray
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class UsersExport implements FromCollection, WithHeadings
{
    /*protected $invoices;
    public function __construct(array $invoices)
    {
        $this->invoices = $invoices;
    }*/
    /*public function array(): array
    {
        
        return $this->invoices;
    }*/
    /*public function array(): array
    {
        /*$arr = [
            ['姓名', '手机号', '年龄','家庭电话'],
            ['张三' , '15210176460', 32 , '0311-82174568']
        ];
        $arr = [
            ['姓名', '手机号', '年龄','家庭电话'],
            ['real_name' => '张三' ,'phone' => '15210176460', 'age' => 32 ,'family_phone' =>  '0311-82174568']
        ];*/
        /*$chapters_list = Student::select('real_name','phone','age','family_phone')->get()->toArray();
        $arr = [
            ['姓名', '手机号', '年龄','家庭电话'],
            $chapters_list
        ];
        return $arr;
        return Chapters::all();
    }*/
    
    
    public function collection() {
        return Student::select('real_name','phone','age','family_phone')->get();
    }
    
    //设置导出文件的表头，如果不设置，只会导出数据库中的记录而不清楚每一项的含义
    public function headings(): array    
    {
        return [
            '姓名',
            '手机号',
            '年龄',
            '家庭电话'
        ];
    }
}