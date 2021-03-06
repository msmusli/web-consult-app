<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

# Models
use App\User;

# Jobs
use App\Jobs\Email\RegistrationStudent;

class StudentController extends Controller
{
    public function list()
    {
        return view('contents.user.student.list');
    }

    public function data(Request $request)
    {
        $users = User::select(DB::raw('users.*'))->has('student')->with([
            'student.profile'
        ]);

        if (!empty($request->is_verified)) {
            $users->whereNotNull('verified_at');
        } else {
            $users->whereNull('verified_at');
        }

        return DataTables::of($users)
        ->addColumn('action_verify', function($user) {
            $verification = empty($user->verified_at) ? '<a class="dropdown-item" href="javascript:;" onclick="verifyUser('.$user->id.')"><i class="bx bx-check mr-1"></i> verifikasi</a>
            <hr>
            <a class="dropdown-item" href="javascript:;"><i class="bx bx-archive mr-1"></i> arsipkan</a>' : '';

            return 
            '<div class="dropdown">
                <span class="bx bxs-cog font-medium-3 dropdown-toggle nav-hide-arrow cursor-pointer" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" role="menu">
                </span>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="javascript:;" onclick="showDetail('.$user->id.')"><i class="bx bxs-user-detail mr-1"></i> rincian</a>
                    '.$verification.'
                    <a class="dropdown-item" href="javascript:;" onclick="updateForm('.$user->id.')"><i class="bx bxs-pencil mr-1"></i> Ubah</a>
                </div>
            </div>';
        })
        ->addColumn('student.profile.age', function($user) {
            return $user->student->profile->age ?? '-';
        })
        ->addColumn('student.profile.semester', function($user) {
            return $user->student->profile->semester ?? '-';
        })
        ->rawColumns([
            'action_verify'
        ])
        ->make(true);
    }

    public function verify(Request $request)
    {
        try {
            $user = User::find($request->id);
            $user->verified_at = Carbon::now()->toDateTimeString();
            $user->save();
        } catch (\Exception $e) {
            $error = "Error! Terjadi kesalahan saat memverifikasi mahasiswa";
        }

        return [
            'status' => empty($error) ? 'success' : 'error',
            'message' => empty($error) ? 'Berhasil memverifikasi mahasiswa' : $error
        ];
    }

    public function detail(Request $request)
    {
        $user = User::find($request->id)->load([
            'student.major.faculty'
        ]);

        return view('contents.user.student.detail', [
            'user' => $user
        ]);
    }

    public function updateForm(Request $request)
    {
        $user = User::find($request->id)->load([
            'student'
        ]);

        return view('contents.user.student.form-update', [
            'user' => $user
        ]);
    }

    public function update(Request $request)
    {
        try {
            $user = User::find($request->id);
            $email = $user->email;
            $user->email = $request->email;
            
            if (!empty($request->password)) {
                $user->password = $request->password;
                $user->password_hint = $request->password;
            }

            $user->save();

            if ($email != $request->email || !empty($request->password)) {
                dispatch(new RegistrationStudent($user->id));
            }
        } catch (\Exception $e) {
            $error = "Error! Terjadi kesalahan saat menyimpan data mahasiswa";
        }

        return [
            'status' => empty($error) ? 'success' : 'error',
            'message' => empty($error) ? 'Berhasil menyimpan data mahasiswa' : $error
        ];
    }

    public function checkEmail(Request $request)
    {
        return User::where('id', '!=', $request->id)->where('email', $request->email)->count();
    }
}
