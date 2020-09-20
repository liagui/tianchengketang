<?php

use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('ld_subjects')->insert([
	        [
	            'id' => 1, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '消防工程师',    
	        ], 
	        [
	            'id' => 2, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '健康管理师',   
	        ],
	        [
	            'id' => 3, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '二级建造师',   
	        ],
	        [
	            'id' => 4, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '一级建造师',   
	        ],
	        [
	            'id' => 5, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '一级造价师',   
	        ],
	        [
	            'id' => 6, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '二级造价师',   
	        ],
	        [
	            'id' => 7, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '监理工程师',   
	        ],
	        [
	            'id' => 8, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '初级经济师',   
	        ],
	        [
	            'id' => 9, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '中级经济师',   
	        ],
	        [
	            'id' => 10, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '执业药师',   
	        ],
	        [
	            'id' => 11, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '会计专业学院',   
	        ],
	        [
	            'id' => 12, 
	            'admin_id' => 0, 
	            'pid' => 0, 
	            'name' =>  '安全工程师',   
	        ],
	        [
	            'id' => 13, 
	            'admin_id' => 0, 
	            'pid' => 0,
	            'name' =>  '学历提升',   
	        ], 
	        [
	            'id' => 14, 
	            'admin_id' => 0, 
	            'pid' => 1, 
	            'name' =>  '一级消防工程师',    
	        ],
	        [
	            'id' => 15, 
	            'admin_id' => 0, 
	            'pid' => 2, 
	            'name' =>  '健康管理师(三级)',    
	        ],
	        [
	            'id' => 16, 
	            'admin_id' => 0, 
	            'pid' => 3, 
	            'name' =>  '市政工程管理',    
	        ],
	        [
	            'id' => 17, 
	            'admin_id' => 0, 
	            'pid' => 3, 
	            'name' =>  '机电工程管理',    
	        ],
	        [
	            'id' => 18, 
	            'admin_id' => 0, 
	            'pid' => 3, 
	            'name' =>  '公路工程管理',    
	        ],
	        [
	            'id' => 19, 
	            'admin_id' => 0, 
	            'pid' => 3, 
	            'name' =>  '建筑工程管理',    
	        ],
	        [
	            'id' => 20, 
	            'admin_id' => 0, 
	            'pid' => 3, 
	            'name' =>  '水利水电工程管理',    
	        ],
	        [
	            'id' => 21, 
	            'admin_id' => 0, 
	            'pid' => 4, 
	            'name' =>  '市政工程管理',    
	        ],
	        [
	            'id' => 22, 
	            'admin_id' => 0, 
	            'pid' => 4, 
	            'name' =>  '机电工程管理',    
	        ],
	        [
	            'id' => 23, 
	            'admin_id' => 0, 
	            'pid' => 4, 
	            'name' =>  '公路工程管理',    
	        ],
	        [
	            'id' => 24, 
	            'admin_id' => 0, 
	            'pid' => 4, 
	            'name' =>  '建筑工程管理',    
	        ],
	        [
	            'id' => 25, 
	            'admin_id' => 0, 
	            'pid' => 4, 
	            'name' =>  '水利水电工程管理',    
	        ],
	        [
	            'id' => 26, 
	            'admin_id' => 0, 
	            'pid' => 5, 
	            'name' =>  '土木建筑',    
	        ],
	        [
	            'id' => 27, 
	            'admin_id' => 0, 
	            'pid' => 5, 
	            'name' =>  '安装管理',    
	        ],
	        [
	            'id' => 28, 
	            'admin_id' => 0, 
	            'pid' => 5, 
	            'name' =>  '交通运输管理',    
	        ],
	        [
	            'id' => 29, 
	            'admin_id' => 0, 
	            'pid' => 5, 
	            'name' =>  '水利工程管理',    
	        ],
	        [
	            'id' => 30, 
	            'admin_id' => 0, 
	            'pid' => 6, 
	            'name' =>  '土木建筑',    
	        ],
	        [
	            'id' => 31, 
	            'admin_id' => 0, 
	            'pid' => 6, 
	            'name' =>  '安装管理',    
	        ],
	        [
	            'id' => 32, 
	            'admin_id' => 0, 
	            'pid' => 7, 
	            'name' =>  '监理工程师',    
	        ],
	        [
	            'id' => 33, 
	            'admin_id' => 0, 
	            'pid' => 8, 
	            'name' =>  '工商管理',    
	        ],
	    	[
	            'id' => 34, 
	            'admin_id' => 0, 
	            'pid' => 8, 
	            'name' =>  '人力资源',    
	        ],
	    	[
	            'id' => 35, 
	            'admin_id' => 0, 
	            'pid' => 9, 
	            'name' =>  '工商管理',    
	        ],
	        [
	            'id' => 36, 
	            'admin_id' => 0, 
	            'pid' => 9, 
	            'name' =>  '人力资源',    
	        ],
	       	[
	            'id' => 37, 
	            'admin_id' => 0, 
	            'pid' => 9, 
	            'name' =>  '财政税收',    
	        ],
	        [
	            'id' => 38, 
	            'admin_id' => 0, 
	            'pid' => 9, 
	            'name' =>  '建筑经济',    
	        ],
	        [
	            'id' => 39, 
	            'admin_id' => 0, 
	            'pid' => 9, 
	            'name' =>  '金融专业',    
	        ],
	        [
	            'id' => 40, 
	            'admin_id' => 0, 
	            'pid' => 10, 
	            'name' =>  '药学专业',    
	        ],
	       	[
	            'id' => 41, 
	            'admin_id' => 0, 
	            'pid' => 10, 
	            'name' =>  '中药学专业',    
	        ],
	        [
	            'id' => 42, 
	            'admin_id' => 0, 
	            'pid' => 11, 
	            'name' =>  '初级会计师',    
	        ],
	        [
	            'id' => 43, 
	            'admin_id' => 0, 
	            'pid' => 11, 
	            'name' =>  '中级会计师',    
	        ],
	        [
	            'id' => 44, 
	            'admin_id' => 0, 
	            'pid' => 12, 
	            'name' =>  '化工专业',    
	        ],
	        [
	            'id' => 45, 
	            'admin_id' => 0, 
	            'pid' => 12, 
	            'name' =>  '矿山专业',    
	        ],
	        [
	            'id' => 46, 
	            'admin_id' => 0, 
	            'pid' => 12, 
	            'name' =>  '建筑专业',    
	        ],
	        [
	            'id' => 47, 
	            'admin_id' => 0, 
	            'pid' => 13, 
	            'name' =>  '高中升大专学历',    
	        ],
	        [
	            'id' => 48, 
	            'admin_id' => 0, 
	            'pid' => 13, 
	            'name' =>  '大专升本科学历',    
	        ],
	    ]);
    }
}
