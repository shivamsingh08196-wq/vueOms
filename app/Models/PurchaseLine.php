<?php namespace App\Models; use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; class PurchaseLine extends Model
{
    use HasFactory;
 protected $guarded = []; public function purchase() { return $this->belongsTo(Purchase::class); } public function item() { return $this->belongsTo(Item::class); } }