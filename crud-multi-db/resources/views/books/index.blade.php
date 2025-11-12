<!DOCTYPE html>
<html>
<head>
    <title>Books for {{ $user->email }}</title>
</head>
<body>
    <h1>Books for User: {{ $user->email }}</h1>

    @if(session('success'))
        <p style="color:green;">{{ session('success') }}</p>
    @endif

    <!-- Add Book Button -->
    <a href="{{ route('books.create', $user->id) }}" style="padding:8px 12px; background:#007bff; color:white; text-decoration:none; border-radius:4px;">
        Add New Book
    </a>
    <a href="{{ route('users.index') }}" style="padding:8px 12px; background:#6c757d; color:white; text-decoration:none; border-radius:4px; margin-left:10px;">
        Back to Users
    </a>

    <br><br>

    <!-- Books Table -->
    <table border="1" cellpadding="5" cellspacing="0" style="width:100%; margin-top:20px;">
        <tr>
            <th>Book ID</th>
            <th>Book Name</th>
            <th>Actions</th>
        </tr>
        @forelse($books as $book)
        <tr>
            <td>{{ $book->book_id }}</td>
            <td>
                <form action="{{ route('books.update', [$user->id, $book->book_id]) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('PUT')
                    <input type="text" name="book_name" value="{{ $book->book_name }}" required style="width:200px;">
                    <button type="submit">Update</button>
                </form>
            </td>
            <td>
                <form action="{{ route('books.destroy', [$user->id, $book->book_id]) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" onclick="return confirm('Delete this book?');">Delete</button>
                </form>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="3" style="text-align:center;">No books found for this user.</td>
        </tr>
        @endforelse
    </table>
</body>
</html>
