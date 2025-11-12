<!DOCTYPE html>
<html>
<head>
    <title>Add Book</title>
</head>
<body>
    <h1>Add Book for User: {{ $user->email }}</h1>

    <form action="{{ route('books.store', $user->id) }}" method="POST">
        @csrf
        <label>Book ID (unique):</label>
        <input type="text" name="book_id" maxlength="100" placeholder="e.g., BOOK001" required style="width:200px; margin-bottom:10px;">
        <br>

        <label>Book Name:</label>
        <input type="text" name="book_name" maxlength="255" placeholder="e.g., Laravel Guide" required style="width:300px; margin-bottom:10px;">
        <br>

        <button type="submit">Add Book</button>
        <a href="{{ route('books.index', $user->id) }}">Cancel</a>
    </form>
</body>
</html>
