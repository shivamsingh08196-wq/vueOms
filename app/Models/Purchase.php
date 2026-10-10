<?php namespace App\Models; use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; class Purchase extends Model
{
    use HasFactory;
 protected $guarded = []; public function client() { return $this->belongsTo(Client::class); } public function lines() { return $this->hasMany(PurchaseLine::class); } public function comments() { return $this->hasMany(PurchaseComment::class); } public function statusLogs() { return $this->hasMany(PurchaseStatusLog::class); } }