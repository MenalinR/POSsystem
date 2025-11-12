<!DOCTYPE html>
<html>
<head>
    <title>All Books (All Users)</title>
</head>
<body>
    <h1>All Books (All Users)</h1>
    <a href="{{ route('users.index') }}">Back to Users</a>
    <br><br>
    @if(session('success'))
        <p style="color:green;">{{ session('success') }}</p>
    @endif
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%;">
        <tr>
            <th>Book ID</th>
            <th>Book Name</th>
            <th>User ID</th>
            <th>User Email</th>
            <th>Actions</th>
        </tr>
        @forelse($books as $book)
        <tr>
            <td>{{ $book->book_id }}</td>
            <td>
                <form action="{{ route('books.update', [$book->user_id, $book->book_id]) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('PUT')
                    <input type="text" name="book_name" value="{{ $book->book_name }}" required style="width:200px;">
                    <button type="submit">Update</button>
                </form>
            </td>
            <td>{{ $book->user_id }}</td>
            <td>{{ $book->user ? $book->user->email : 'N/A' }}</td>
            <td>
                <a href="{{ route('books.edit', [$book->user_id, $book->book_id]) }}">Edit</a>
                <form action="{{ route('books.destroy', [$book->user_id, $book->book_id]) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this book?');">Delete</button>
                </form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center;">No books found.</td>
        </tr>
        @endforelse
    </table>
</body>
</html>
