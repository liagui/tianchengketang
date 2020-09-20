<?php
namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class ExamExport implements FromCollection, WithHeadings {
    public function collection() {
        return Student::select('real_name','phone','age','family_phone')->get();
    }

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
