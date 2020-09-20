<?php
namespace App\Exports;
use App\Invoice;
use Maatwebsite\Excel\Concerns\FromArray;
class InvoicesExport implements FromArray
{
    protected $invoices;
    public function __construct(array $invoices)
    {
        $this->invoices = $invoices;
    }
    public function array(): array
    {
        return $this->invoices;
    }
}
