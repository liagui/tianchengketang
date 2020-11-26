<?php
namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, AuthorizableContract, JWTSubject
{
    use Authenticatable, Authorizable;

    public $table = 'ld_student';
    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id','head_icon', 'real_name', 'phone', 'nickname', 'sign', 'papers_type', 'papers_num','is_forbid'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'create_at',
        'update_at',
        'is_forbid',
        'reg_source',
        'state_status',
        'enroll_status',
        'remark',
        'family_phone',
        'age',
        'address',
        'admin_id',
        'birthday',
        'province_id' ,
        'city_id' ,
        'sex' ,
        'address_locus',
        'educational',
        'office_phone',
        'contact_people',
        'contact_phone',
        'email',
        'qq',
        'wechat',
        'device',
        'user_type',
        'token'
    ];


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
