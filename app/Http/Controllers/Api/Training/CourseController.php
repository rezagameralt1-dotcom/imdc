<?php

namespace App\Http\Controllers\Api\Training;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Training\CreateCourseRequest;
use App\Http\Requests\Training\UpdateCourseRequest;
use App\Services\Training\TrainingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use DomainException;

class CourseController extends ApiController
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $isTeacherOrAdmin = $user->hasRole('Admin') || $user->hasRole('Teacher');

            $filters = [];
            if ($request->has('status')) {
                $filters['status'] = $request->input('status');
            }
            if ($request->has('level')) {
                $filters['level'] = $request->input('level');
            }
            if ($request->has('teacher_user_id')) {
                $filters['teacher_user_id'] = $request->input('teacher_user_id');
            }

            $courses = $this->trainingService->listCourses($filters, $isTeacherOrAdmin);
            
            return $this->successResponse($courses);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve courses: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $course = $this->trainingService->getCourse($id);
            
            return $this->successResponse($course);
        } catch (DomainException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve course: ' . $e->getMessage(), 500);
        }
    }

    public function store(CreateCourseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        try {
            $course = $this->trainingService->createCourse(
                $data['title'],
                $user->id, // teacher_user_id
                $data,
                $user->id, // actor_user_id
                $this->traceId()
            );
            
            return $this->successResponse($course, 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create course: ' . $e->getMessage(), 500);
        }
    }

    public function update(UpdateCourseRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        try {
            $course = $this->trainingService->updateCourse(
                $id,
                $data,
                $user->id, // actor_user_id
                $this->traceId()
            );
            
            return $this->successResponse($course);
        } catch (DomainException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update course: ' . $e->getMessage(), 500);
        }
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        try {
            $course = $this->trainingService->publishCourse(
                $id,
                $user->id, // actor_user_id
                $this->traceId()
            );
            
            return $this->successResponse($course);
        } catch (DomainException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to publish course: ' . $e->getMessage(), 500);
        }
    }

    public function enroll(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);
        $user = $request->user();

        try {
            $enrollment = $this->trainingService->enroll(
                $id,
                $user->id,
                $data['idempotency_key'],
                $user->id, // actor_user_id
                $this->traceId()
            );
            
            // Check if enrollment was just created (firstOrCreate returns wasRecentlyCreated)
            // If idempotency key was used and enrollment already existed, wasRecentlyCreated will be false
            $wasRecentlyCreated = isset($enrollment->wasRecentlyCreated) ? $enrollment->wasRecentlyCreated : false;
            return $this->successResponse($enrollment, $wasRecentlyCreated ? 201 : 200);
        } catch (DomainException $e) {
            if (str_contains($e->getMessage(), 'Idempotency key already used')) {
                return $this->errorResponse($e->getMessage(), 409);
            }
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to enroll: ' . $e->getMessage(), 500);
        }
    }
}
