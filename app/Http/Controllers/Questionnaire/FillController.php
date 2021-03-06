<?php

namespace App\Http\Controllers\Questionnaire;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

# Models
use App\Models\Questionnaire\Questionnaire;
use App\Models\Questionnaire\Student_questionnaire;
use App\Models\Quiz\Student_quiz;

# Jobs
use App\Jobs\Quesionnaire\EmailCongratulation;

class FillController extends Controller
{
    public function check()
    {
        $questionnaire = Questionnaire::whereDoesntHave('student_questionnaire', function($query) {
            $query->where('student_id', Auth::user()->student->id)->where('status', 'pre');
        })->orderBy('code', 'ASC')->first(); 

        if (!empty($questionnaire)) {
            return redirect()->route('questionnaire.fill.form', ['questionnaire' => Crypt::encrypt($questionnaire->id)]);
        } 

        return redirect()->route('questionnaire.fill.done');        
    }

    public function form(Request $request)
    {
        try {
            $questionnaire = Questionnaire::find(Crypt::decrypt($request->questionnaire))->load([
                'questions' => function($query) {
                    $query->with([
                        'answers'
                    ]);
                }
            ]);

            return view('contents.questionnaire.fill.form-questionnaire', [
                'questionnaire' => $questionnaire
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return redirect()->route('questionnaire.fill.check');
        }
    }

    public function submit(Request $request)
    {
        $questionnaire = Student_questionnaire::create([
            "student_id" => Auth::user()->student->id,
            "questionnaire_id" => $request->questionnaire_id,
            "status" => "pre"
        ]);

        foreach ($request->answers as $key => $value) {
            if (is_array($value)) {
                $questionnaire->student_answers()->create([
                    "questionnaire_question_id" => $key,
                    "answer" => implode(",",$value),
                ]);
            } else {
                $questionnaire->student_answers()->create([
                    "questionnaire_question_id" => $key,
                    "answer" => $value,
                ]);
            }
        }

        try {
            $poin = Student_questionnaire::where('id', $questionnaire->id)->withCount([
                'student_answers AS poin_sum' => function($query) {
                    $query->select(DB::raw("SUM(poin) as poinsum"))->join('questionnaire_answers', 'student_questionnaire_answer.answer', '=', 'questionnaire_answers.id');
                }
            ])->first()->poin_sum;

        } catch (\Exception $e) {
            Log::warning($e->getMessage());

            $poin = 0;
        }

        foreach ($questionnaire->questionnaire->results as $result) {
            if ($poin >= $result->score_from && $poin <= $result->score_to) {
                $questionnaire->questionnaire_result_id = $result->id;
                break;
            }
        }

        $questionnaire->save();

        if ($questionnaire->questionnaire_id == 1) {

            $student = Auth::user()->student;
            if ($questionnaire->questionnaire_result_id == 2) {                
                $student->need_consult = true;
            } else {
                $student->need_consult = false;
            }
            $student->save();

            dispatch(new EmailCongratulation(Auth::user()->id));
        }

        return redirect()->route('questionnaire.fill.check');
    }

    public function done()
    {
        $quiz_count = Student_quiz::where('student_id', Auth::user()->student->id)->count();

        if ($quiz_count == 0) {            
            $url_next = Auth::user()->student->need_consult ? route('counseling.form.fill') : route('quiz.required.check');

            return view('contents.questionnaire.fill.done', [
                'url_next' => $url_next
            ]);
        }

        return redirect()->route('quiz.required.check');
    }
}
