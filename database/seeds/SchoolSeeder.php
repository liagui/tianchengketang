<?php

use Illuminate\Database\Seeder;

class SchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('ld_school')->insert([
            [
                'id' => 1,
                'admin_id' => 0, 
                'name' => '北京第二分校', 
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ], 
            [
                'id' => 2, 
                'admin_id' => 0,
                'name' => '北京第六分校', 
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ],  
            [
                'id' => 3, 
                'admin_id' => 0,
                'name' => '北京第七分校',
                'logo_url' => '',
                'introduce' => '',
                'dns' => '', 
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ], 
            [
                'id' => 4,
                'admin_id' => 0, 
                'name' => '北京第八分校 ', 
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ],
            [
                'id' => 5,
                'admin_id' => 0, 
                'name' => '北京第十分校', 
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ], 
            [
                'id' => 6, 
                'admin_id' => 0,
                'name' => '北京燕郊分校',
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00', 
            ],  
            [
                'id' => 7, 
                'admin_id' => 0,
                'name' => '北京十三分校',
                'logo_url' => '',
                'introduce' => '',
                'dns' => '', 
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ], 
            [
                'id' => 8,
                'admin_id' => 0, 
                'name' => '北京十七分校',
                'logo_url' => '',
                'introduce' => '',
                'dns' => '', 
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ],
            [
                'id' => 9, 
                'admin_id' => 0,
                'name' => '北京房山分校', 
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ], 
            [
                'id' => 10,
                'admin_id' => 0, 
                'name' => '北京密云分校', 
                'logo_url' => '',
                'introduce' => '',
                'dns' => '',
                'create_time' => '2020-06-03 16:00',
                'update_time' => '2020-06-03 16:00',
            ]

        ]);
    }
}
