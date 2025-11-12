<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Book;

class UserModel extends Model
{
    use HasFactory;

    // This application uses a `user` table with only `id` and `email` columns.
    // Disable automatic timestamps to avoid created_at/updated_at errors.
    public $timestamps = false;

    protected $table = 'user';
    // Allow mass-assignment of id when provided (user requested ability to set id)
    protected $fillable = ['id', 'email'];

    // Relation: one user has many books
    public function books()
    {
        return $this->hasMany(Book::class, 'user_id', 'id');
    }
}

