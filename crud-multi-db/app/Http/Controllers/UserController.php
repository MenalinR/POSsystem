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

    // Sync ALL users to DB2 (updates added/modified records and removes deleted ones)
    public function syncAll()
    {
                // ===== SYNC BOOKS =====
        // Step 4: Get all books from DB1
        $db1Books = \App\Models\Book::all();

        // Step 5: Sync all DB1 books to DB2 (insert or update)
        foreach ($db1Books as $book) {
            DB::connection('mysql_second')->table('book')->updateOrInsert(
                ['book_id' => $book->book_id],
                [
                    'user_id' => $book->user_id,
                    'book_name' => $book->book_name,
                ]
            );
            
        $db1Users = UserModel::all();

        // Step 1: Sync all DB1 users to DB2 (insert or update)
        foreach ($db1Users as $user) {
            DB::connection('mysql_second')->table('user')->updateOrInsert(
                ['id' => $user->id],
                ['email' => $user->email]
            );
        }

        // Step 2: Get all user IDs from DB1 and DB2
        $db1Ids = $db1Users->pluck('id')->toArray();
        $db2AllIds = DB::connection('mysql_second')->table('user')->pluck('id')->toArray();

        // Step 3: Delete users from DB2 that don't exist in DB1 (deleted records)
        $idsToDelete = array_diff($db2AllIds, $db1Ids);
        if (!empty($idsToDelete)) {
            DB::connection('mysql_second')->table('user')->whereIn('id', $idsToDelete)->delete();
        }


        }

        // Step 6: Get all book IDs from DB1 and DB2
        $db1BookIds = $db1Books->pluck('book_id')->toArray();
        $db2AllBookIds = DB::connection('mysql_second')->table('book')->pluck('book_id')->toArray();

        // Step 7: Delete books from DB2 that don't exist in DB1 (deleted records)
        $bookIdsToDelete = array_diff($db2AllBookIds, $db1BookIds);
        if (!empty($bookIdsToDelete)) {
            DB::connection('mysql_second')->table('book')->whereIn('book_id', $bookIdsToDelete)->delete();
        }

        return back()->with('success', 'All users and books synced to DB2! (added, updated, and deleted records synchronized)');
    }
}
