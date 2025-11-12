<!DOCTYPE html>
<html>
<head>
    <title>Users CRUD with Sync</title>
</head>
<body>
    <h1>Users</h1>

    @if(session('success'))
        <p style="color:green;">{{ session('success') }}</p>
    @endif

    <!-- Add User -->
    <form action="{{ route('users.store') }}" method="POST" style="display:inline-block;">
        @csrf
        <input type="number" name="id" placeholder="ID (optional)" style="width:110px; margin-right:8px;">
        <input type="email" name="email" placeholder="Email" required style="margin-right:8px;">
        <button type="submit">Add</button>
    </form>

    <form action="{{ route('users.syncAll') }}" method="POST" style="display:inline-block; margin-left:10px;">
        @csrf
        <button type="submit">Sync All</button>
    </form>
    <a href="{{ route('books.all') }}" style="display:inline-block; margin-left:10px; padding:8px 12px; background:#28a745; color:white; text-decoration:none; border-radius:4px;">All Book Views</a>

    <br>

    <!-- Users Table -->
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Actions</th>
        </tr>
        @foreach($users as $user)
        <tr>
            <td>{{ $user->id }}</td>
            <td>
                <form action="{{ route('users.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="email" name="email" value="{{ $user->email }}" required>
                    <button type="submit">Update</button>
                </form>
            </td>
            <td>
                <a href="{{ route('books.index', $user->id) }}" style="padding:5px 10px; background:#17a2b8; color:white; text-decoration:none; border-radius:3px; display:inline-block; margin-right:5px;">View Books</a>

                <form action="{{ route('users.destroy', $user->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
    </table>
</body>
</html>
