<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserModel;

class Book extends Model
{
    use HasFactory;

    protected $table = 'book';
    public $timestamps = false; // match migration (no timestamps)
    protected $primaryKey = 'book_id';
    // book_id is a string (varchar) in your DB, not an auto-incrementing integer
    public $incrementing = false;
    protected $keyType = 'string';

    // Allow mass-assignment of these fields
    protected $fillable = ['book_id', 'user_id', 'book_name'];

    protected $casts = [
        'user_id' => 'integer',
    ];

    // Relation to user
    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
}
