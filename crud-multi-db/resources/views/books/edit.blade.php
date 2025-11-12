<!DOCTYPE html>
<html>
<head>
    <title>Edit Book</title>
</head>
<body>
    <h1>Edit Book</h1>

    <form action="{{ route('books.update', [$user->id, $book->book_id]) }}" method="POST">
        @csrf
        @method('PUT')

        <label>Book ID:</label>
        <input type="text" value="{{ $book->book_id }}" disabled style="width:200px; margin-bottom:10px;">
        <br>

        <label>Book Name:</label>
        <input type="text" name="book_name" value="{{ $book->book_name }}" maxlength="255" required style="width:300px; margin-bottom:10px;">
        <br>

        <button type="submit">Update Book</button>
        <a href="{{ route('books.index', $user->id) }}">Cancel</a>
    </form>
</body>
</html>
