<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardStatsController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\MonthlyReportController;
use App\Http\Controllers\PlannedStudySessionController;
use App\Http\Controllers\StudyRecordController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\YearlyOverviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/stats', DashboardStatsController::class)->name('dashboard.stats');
    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.legacy');
    Route::get('/calendar', CalendarController::class)->name('calendar.index');
    Route::get('/calendario', CalendarController::class)->name('calendar.legacy');
    Route::get('/ano', YearlyOverviewController::class)->name('year.index');
    Route::get('/materias', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
    Route::get('/metas', [GoalController::class, 'index'])->name('goals.index');
    Route::get('/cronograma', [PlannedStudySessionController::class, 'index'])->name('schedule.index');
    Route::post('/cronograma', [PlannedStudySessionController::class, 'store'])->name('schedule.store');
    Route::post('/cronograma/{plannedSession}/executar', [PlannedStudySessionController::class, 'execute'])->name('schedule.execute');
    Route::get('/cronograma/{plannedSession}/editar', [PlannedStudySessionController::class, 'edit'])->name('schedule.edit');
    Route::put('/cronograma/{plannedSession}', [PlannedStudySessionController::class, 'update'])->name('schedule.update');
    Route::patch('/cronograma/{plannedSession}/cancelar', [PlannedStudySessionController::class, 'cancel'])->name('schedule.cancel');
    Route::patch('/cronograma/{plannedSession}/reagendar', [PlannedStudySessionController::class, 'reschedule'])->name('schedule.reschedule');
    Route::post('/metas', [GoalController::class, 'store'])->name('goals.store');
    Route::get('/metas/{goal}/editar', [GoalController::class, 'edit'])->name('goals.edit');
    Route::put('/metas/{goal}', [GoalController::class, 'update'])->name('goals.update');
    Route::patch('/metas/{goal}/arquivar', [GoalController::class, 'archive'])->name('goals.archive');
    Route::get('/relatorios/mensal', MonthlyReportController::class)->name('reports.monthly');
    Route::post('/study-records', [StudyRecordController::class, 'store'])->name('study-records.store');
    Route::put('/study-records/{studyRecord}', [StudyRecordController::class, 'update'])->name('study-records.update');
});

require __DIR__.'/auth.php';
