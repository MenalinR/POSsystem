<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserModel;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    // Show all users and form in one page
    public function index()
    {
        $users = UserModel::all();
        return view('users.index', compact('users'));
    }

    // Add user to DB1
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'nullable|integer',
            'email' => 'required|email',
        ]);

        // include id if provided (will be ignored by DB if auto-increment and not allowed)
        UserModel::create($request->only(['id', 'email']));
        return back()->with('success', 'User added to DB1!');
    }

    // Update user in DB1
    public function update(Request $request, $id)
    {
        $request->validate(['email' => 'required|email']);
        $user = UserModel::findOrFail($id);
        $user->update($request->only('email'));
        return back()->with('success', 'User updated in DB1!');
    }

    // Delete user from DB1
    public function destroy($id)
    {
        $user = UserModel::findOrFail($id);
        // Delete from DB1 only
        $user->delete();

        return back()->with('success', 'User deleted from DB1!');
    }

    // Sync user to DB2
    public function sync($id)
    {
        $user = UserModel::findOrFail($id);
        DB::connection('mysql_second')->table('user')->updateOrInsert(
            ['email' => $user->email], // match by email
            ['email' => $user->email]  // insert or update
        );
        return back()->with('success', 'User synced to DB2!');
    }

    // Sync ALL users to DB2
    public function syncAll()
    {
        $users = UserModel::all();

        foreach ($users as $user) {
            // Use id as the matching key so primary keys are preserved if desired
            DB::connection('mysql_second')->table('user')->updateOrInsert(
                ['id' => $user->id],
                ['email' => $user->email]
            );
        }

        return back()->with('success', 'All users synced to DB2!');
    }
}
