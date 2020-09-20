<?php

use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('ld_lessons')->insert([
	        [
	            'id' => '1', 
	            'admin_id' => '1', 
	            'title' => '1', 
	            'keyword' =>  '关键词', 
	            'cover' =>    'aa.jpg',      
	            'description' => '描述',    
	            'introduction' => '介绍' , 
	            'url' => '["aa.mp4", "bb.mp4", "cc.mp4"]',            
	            'is_public' => 0,      
	            'price'  => 20.00,         
	            'favorable_price'  => 10.00,
	            'method'  => 1,        
	            'ttl' => 3,           
	            'buy_num'  => 300,       
	            'status'  => 0,        
	            'is_del'  => 0,        
	            'is_forbid' =>0 
	        ], 
	        [
	            'id' => '2', 
	            'admin_id' => '1', 
	            'title' => '1', 
	            'keyword' =>  '关键词', 
	            'cover' =>    'aa.jpg',      
	            'description' => '描述',    
	            'introduction' => '介绍',  
	            'url' => '["aa.mp4", "bb.mp4", "cc.mp4"]',            
	            'is_public' => 0,      
	            'price'  => 20.00,         
	            'favorable_price'  => 10.00,
	            'method'  => 1,        
	            'ttl' => 3,           
	            'buy_num'  => 300,       
	            'status'  => 0,        
	            'is_del'  => 0,        
	            'is_forbid' =>0 
	        ],  
	        ]);
    }
}
