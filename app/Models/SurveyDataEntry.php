<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyDataEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_response_id',
        'tahun',
        'nama_data',
        'status_perolehan',
        'jenis_sumber',
        'digunakan_pembangunan',
        'tingkat_kepuasan',
    ];

    /**
     * Relasi ke survey response
     */
    public function surveyResponse()
    {
        return $this->belongsTo(SurveyResponse::class);
    }
}