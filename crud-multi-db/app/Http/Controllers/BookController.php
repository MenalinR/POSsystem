<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Book;
use App\Models\UserModel;

class BookController extends Controller
{
    // Show all books for a specific user
    public function index($userId)
    {
        $user = UserModel::findOrFail($userId);
        $books = Book::where('user_id', $userId)->get();
        return view('books.index', compact('user', 'books'));
    }

    // Show form to add a new book for a user
    public function create($userId)
    {
        $user = UserModel::findOrFail($userId);
        return view('books.create', compact('user'));
    }

    // Add book to DB1
    public function store(Request $request, $userId)
    {
        $user = UserModel::findOrFail($userId);

        $request->validate([
            'book_id' => 'required|string|max:100|unique:book',
            'book_name' => 'required|string|max:255',
        ]);

        Book::create([
            'book_id' => $request->book_id,
            'user_id' => $userId,
            'book_name' => $request->book_name,
        ]);

        return redirect()->route('books.index', $userId)->with('success', 'Book added to DB1!');
    }

    // Show form to edit a book
    public function edit($userId, $bookId)
    {
        $user = UserModel::findOrFail($userId);
        $book = Book::where('book_id', $bookId)->where('user_id', $userId)->firstOrFail();
        return view('books.edit', compact('user', 'book'));
    }

    // Update book in DB1
    public function update(Request $request, $userId, $bookId)
    {
        $user = UserModel::findOrFail($userId);
        $book = Book::where('book_id', $bookId)->where('user_id', $userId)->firstOrFail();

        $request->validate([
            'book_name' => 'required|string|max:255',
        ]);

        $book->update(['book_name' => $request->book_name]);

        return redirect()->route('books.index', $userId)->with('success', 'Book updated in DB1!');
    }

    // Delete book from DB1
    public function destroy($userId, $bookId)
    {
        $user = UserModel::findOrFail($userId);
        $book = Book::where('book_id', $bookId)->where('user_id', $userId)->firstOrFail();
        $book->delete();

        return redirect()->route('books.index', $userId)->with('success', 'Book deleted from DB1!');
    }

    // Show all books across all users
    public function allBooks()
    {
        $books = \App\Models\Book::with('user')->get();
        return view('books.all', compact('books'));
    }
}
