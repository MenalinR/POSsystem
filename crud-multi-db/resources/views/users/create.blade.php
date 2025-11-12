<!DOCTYPE html>
<html>
<head>
    <title>Add User</title>
</head>
<body>
   <h1>Add User</h1>
<form action="{{ route('users.store') }}" method="POST">
    @csrf
    <label>Email:</label>
    <input type="email" name="email" required>
    <button type="submit">Add</button>
</form>
<a href="{{ route('users.index') }}">Back</a>

</body>
</html>
