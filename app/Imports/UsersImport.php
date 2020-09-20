<?php
namespace App\Imports;

//第一种方法
use App\Models\Student;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;


class UsersImport implements ToCollection
{
    public function collection(Collection $rows)
    {

    }
}

/*use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;

class UsersImport implements ToModel {
    public function model(array $row)
    {
        
        //echo "<pre>";
        //print_r($row);
        return new Student($row);
    }
}*/