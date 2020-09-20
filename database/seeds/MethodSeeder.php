<?php

use Illuminate\Database\Seeder;

class MethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('ld_methods')->insert([
	        [
	            'id' => 1,
	            'admin_id' => 0, 
	            'name' => '直播', 
	        ], 
	        [
	            'id' => 2, 
	            'admin_id' => 0,
	            'name' => '录播', 
	        ],  
	        [
	            'id' => 3, 
	            'admin_id' => 0,
	            'name' => '面授', 
	        ], 
	        [
	            'id' => 4,
	            'admin_id' => 0, 
	            'name' => '其他', 
	        ],

	    ]);
    }
}
