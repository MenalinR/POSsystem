<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
</head>
<body>
    <h1>Edit User</h1>
<form action="{{ route('users.update', $user->id) }}" method="POST">
    @csrf
    @method('PUT')
    <label>Email:</label>
    <input type="email" name="email" value="{{ $user->email }}" required>
    <button type="submit">Update</button>
</form>
<a href="{{ route('users.index') }}">Back</a>
</body>
</html>
