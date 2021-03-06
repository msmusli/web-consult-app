<?php

namespace App\Models\Profile;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Student extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;
    
    protected $table = 'students';
    protected $fillable = [
        "student_id_number",
        "first_name",
        "last_name",
        "gender_id",
        "place_of_birth",
        "date_of_birth",
        "village_id",
        "address",
        "phone_number",
        "current_address",
        "high_school_name",
        "major_id",
        "semester",
        "year",
        "user_id",

        "need_consult",
        "approve_consent",
        "is_done"
    ];

    public function gender()
    {
        return $this->belongsTo('App\Models\Profile\Gender', 'gender_id', 'id');
    }

    public function village()
    {
        return $this->belongsTo('App\Models\Area\Village', 'village_id', 'id');
    }

    public function major()
    {
        return $this->belongsTo('App\Models\University\Major', 'major_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function profile()
    {
        return $this->hasOne('App\Models\Profile\Student_profile', 'student_id', 'id')->latest();
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function filled_questionnaires()
    {
        return $this->hasMany('App\Models\Questionnaire\Student_questionnaire', 'student_id', 'id');
    }

    public function evaluation()
    {
        return $this->hasOne('App\Models\Evaluation\Student_evaluation', 'student_id', 'id')->latest();
    }
}
