<?php

namespace App\Services\Training;

use App\Models\IdempotencyKey;
use App\Models\Training\Course;
use App\Models\Training\Enrollment;
use App\Models\Training\SkillNft;
use App\Models\Training\TrainingEvent;
use App\Dids\Models\DidProfile;
use App\Services\Did\DidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use DomainException;

class TrainingService
{
    public function __construct(
        private readonly DidService $didService,
    ) {
    }

    /**
     * Convert integer user ID to UUID string format (deterministic)
     * Since users.id is integer but training tables use UUID, we convert it
     * Uses MD5 hash to generate deterministic UUID v4-like string
     *
     * @param int $userId
     * @return string
     */
    private function userIdToUuid(int $userId): string
    {
        // Generate deterministic UUID from user ID using MD5 hash
        // Use a fixed namespace string for consistency
        $namespace = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $name = "user:{$userId}";
        
        // Convert namespace UUID to binary (remove dashes, convert hex to binary)
        $namespaceHex = str_replace('-', '', $namespace);
        $namespaceBin = '';
        for ($i = 0; $i < strlen($namespaceHex); $i += 2) {
            $namespaceBin .= chr(hexdec(substr($namespaceHex, $i, 2)));
        }
        
        $hash = md5($namespaceBin . $name);
        
        // Format as UUID v4 (but deterministic)
        return sprintf(
            '%08s-%04s-%04s-%04s-%012s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    /**
     * List courses (filtered by status for non-teachers)
     *
     * @param array $filters
     * @param bool $isTeacherOrAdmin
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function listCourses(array $filters = [], bool $isTeacherOrAdmin = false)
    {
        $query = Course::on('core');

        if (!$isTeacherOrAdmin) {
            // Regular users only see published courses
            $query->where('status', 'published');
        } elseif (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (isset($filters['teacher_user_id'])) {
            $query->where('teacher_user_id', $filters['teacher_user_id']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get course by ID
     *
     * @param string $courseId
     * @return Course
     * @throws DomainException
     */
    public function getCourse(string $courseId): Course
    {
        if (!Str::isUuid($courseId)) {
            throw new DomainException("Invalid course_id format: must be UUID");
        }

        $course = Course::on('core')->find($courseId);
        if (!$course) {
            throw new DomainException("Course not found: {$courseId}");
        }

        return $course;
    }

    /**
     * Create course (idempotent via title + teacher_user_id)
     *
     * @param string $title
     * @param int $teacherUserId
     * @param array $options
     * @param int|null $actorUserId
     * @param string|null $traceId
     * @return Course
     * @throws DomainException
     */
    public function createCourse(
        string $title,
        int $teacherUserId,
        array $options = [],
        ?int $actorUserId = null,
        ?string $traceId = null
    ): Course {
        $teacherUserUuid = $this->userIdToUuid($teacherUserId);

        return DB::connection('core')->transaction(function () use ($title, $teacherUserUuid, $options, $actorUserId, $traceId) {
            // Idempotency: same title + teacher
            $course = Course::on('core')->firstOrCreate(
                [
                    'title' => $title,
                    'teacher_user_id' => $teacherUserUuid,
                ],
                [
                    'description' => $options['description'] ?? null,
                    'level' => $options['level'] ?? null,
                    'language' => $options['language'] ?? null,
                    'status' => $options['status'] ?? 'draft',
                ]
            );

            if (!$course->wasRecentlyCreated) {
                // Update mutable fields
                $course->update([
                    'description' => $options['description'] ?? $course->description,
                    'level' => $options['level'] ?? $course->level,
                    'language' => $options['language'] ?? $course->language,
                ]);
            }

            $this->logEvent('course_created_or_updated', $course->toArray(), $actorUserId, $traceId);
            return $course;
        });
    }

    /**
     * Update course
     *
     * @param string $courseId
     * @param array $data
     * @param int|null $actorUserId
     * @param string|null $traceId
     * @return Course
     * @throws DomainException
     */
    public function updateCourse(
        string $courseId,
        array $data,
        ?int $actorUserId = null,
        ?string $traceId = null
    ): Course {
        if (!Str::isUuid($courseId)) {
            throw new DomainException("Invalid course_id format: must be UUID");
        }

        $course = Course::on('core')->find($courseId);
        if (!$course) {
            throw new DomainException("Course not found: {$courseId}");
        }

        $course->update($data);
        $this->logEvent('course_updated', $course->toArray(), $actorUserId, $traceId);
        return $course->fresh();
    }

    /**
     * Publish course
     *
     * @param string $courseId
     * @param int|null $actorUserId
     * @param string|null $traceId
     * @return Course
     * @throws DomainException
     */
    public function publishCourse(
        string $courseId,
        ?int $actorUserId = null,
        ?string $traceId = null
    ): Course {
        if (!Str::isUuid($courseId)) {
            throw new DomainException("Invalid course_id format: must be UUID");
        }

        $course = Course::on('core')->find($courseId);
        if (!$course) {
            throw new DomainException("Course not found: {$courseId}");
        }

        $course->update(['status' => 'published']);
        $this->logEvent('course_published', $course->toArray(), $actorUserId, $traceId);
        return $course->fresh();
    }

    /**
     * Enroll user in course (idempotent via course_id + user_id + idempotency_key)
     *
     * @param string $courseId
     * @param int $userId
     * @param string|null $idempotencyKey
     * @param int|null $actorUserId
     * @param string|null $traceId
     * @return Enrollment
     * @throws DomainException
     */
    public function enroll(
        string $courseId,
        int $userId,
        ?string $idempotencyKey = null,
        ?int $actorUserId = null,
        ?string $traceId = null
    ): Enrollment {
        if (!Str::isUuid($courseId)) {
            throw new DomainException("Invalid course_id format: must be UUID");
        }

        $course = Course::on('core')->find($courseId);
        if (!$course) {
            throw new DomainException("Course not found: {$courseId}");
        }

        if ($course->status !== 'published') {
            throw new DomainException("Course is not published (current status: {$course->status})");
        }

        return DB::connection('core')->transaction(function () use ($courseId, $userId, $idempotencyKey, $actorUserId, $traceId) {
            // Check idempotency key FIRST (before any side-effects)
            if ($idempotencyKey) {
                $idempotencyKey = substr(trim($idempotencyKey), 0, 128);
                $requestHash = md5(json_encode(['course_id' => $courseId, 'user_id' => $userId]));

                $existingIdempotency = IdempotencyKey::on('core')
                    ->where('user_id', $userId)
                    ->where('scope', 'training.enroll')
                    ->where('key', $idempotencyKey)
                    ->first();

                if ($existingIdempotency) {
                    if ($existingIdempotency->request_hash !== $requestHash) {
                        throw new DomainException('Idempotency key already used with different payload');
                    }

                    // Return stored enrollment
                    $enrollmentId = $existingIdempotency->resource_id;
                    $enrollment = Enrollment::on('core')->find($enrollmentId);
                    if ($enrollment) {
                        return $enrollment;
                    }
                }
            }

            // Get or create DID for user
            $didProfile = $this->didService->getOrCreate($userId);
            $didId = $didProfile->id;
            $userUuid = $this->userIdToUuid($userId);

            // Idempotency: unique constraint on (course_id, user_id)
            $enrollment = Enrollment::on('core')->firstOrCreate(
                [
                    'course_id' => $courseId,
                    'user_id' => $userUuid,
                ],
                [
                    'did_id' => $didId,
                    'status' => 'enrolled',
                    'enrolled_at' => now(),
                ]
            );

            // Store idempotency key if provided
            if ($idempotencyKey && $enrollment->wasRecentlyCreated) {
                $requestHash = md5(json_encode(['course_id' => $courseId, 'user_id' => $userId]));
                $responseArray = [
                    'success' => true,
                    'data' => $enrollment->toArray(),
                    'error' => null,
                ];
                $responseBody = json_encode($responseArray);

                try {
                    IdempotencyKey::on('core')->create([
                        'user_id' => $userId,
                        'scope' => 'training.enroll',
                        'key' => $idempotencyKey,
                        'request_hash' => $requestHash,
                        'response_code' => 201,
                        'response_body' => $responseBody,
                        'resource_id' => $enrollment->id,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle race condition - fetch existing and return
                    if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), '23000')) {
                        $existingIdempotency = IdempotencyKey::on('core')
                            ->where('user_id', $userId)
                            ->where('scope', 'training.enroll')
                            ->where('key', $idempotencyKey)
                            ->first();

                        if ($existingIdempotency && $existingIdempotency->request_hash === $requestHash) {
                            $enrollmentId = $existingIdempotency->resource_id;
                            $enrollment = Enrollment::on('core')->find($enrollmentId);
                            if ($enrollment) {
                                return $enrollment;
                            }
                        }
                    }
                    throw $e;
                }
            }

            $this->logEvent('enrollment_created', $enrollment->toArray(), $actorUserId, $traceId);
            return $enrollment;
        });
    }

    /**
     * Complete enrollment and issue skill NFT (idempotent via idempotency_key)
     *
     * @param string $enrollmentId
     * @param string|null $idempotencyKey
     * @param int|null $actorUserId
     * @param string|null $traceId
     * @return array{enrollment: Enrollment, skill_nft: SkillNft}
     * @throws DomainException
     */
    public function completeEnrollment(
        string $enrollmentId,
        ?string $idempotencyKey = null,
        ?int $actorUserId = null,
        ?string $traceId = null
    ): array {
        if (!Str::isUuid($enrollmentId)) {
            throw new DomainException("Invalid enrollment_id format: must be UUID");
        }

        return DB::connection('core')->transaction(function () use ($enrollmentId, $idempotencyKey, $actorUserId, $traceId) {
            $enrollment = Enrollment::on('core')->find($enrollmentId);
            if (!$enrollment) {
                throw new DomainException("Enrollment not found: {$enrollmentId}");
            }

            // Check idempotency key FIRST
            if ($idempotencyKey) {
                $idempotencyKey = substr(trim($idempotencyKey), 0, 128);
                $requestHash = md5(json_encode(['enrollment_id' => $enrollmentId]));

                $existingIdempotency = IdempotencyKey::on('core')
                    ->where('user_id', $actorUserId ?? $enrollment->user_id)
                    ->where('scope', 'training.complete')
                    ->where('key', $idempotencyKey)
                    ->first();

                if ($existingIdempotency) {
                    if ($existingIdempotency->request_hash !== $requestHash) {
                        throw new DomainException('Idempotency key already used with different payload');
                    }

                    // Return stored skill NFT
                    $skillNftId = $existingIdempotency->resource_id;
                    $skillNft = SkillNft::on('core')->find($skillNftId);
                    if ($skillNft) {
                        // Ensure enrollment is marked as completed
                        if ($enrollment->status !== 'completed') {
                            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
                        }
                        return ['enrollment' => $enrollment->fresh(), 'skill_nft' => $skillNft];
                    }
                }
            }

            // Update enrollment
            if ($enrollment->status !== 'completed') {
                $enrollment->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            }

            // Issue skill NFT (idempotent via unique constraint)
            $skillNft = SkillNft::on('core')->firstOrCreate(
                [
                    'course_id' => $enrollment->course_id,
                    'user_id' => $enrollment->user_id,
                ],
                [
                    'did_id' => $enrollment->did_id,
                    'metadata' => [
                        'course_title' => $enrollment->course->title ?? 'Unknown',
                        'completed_at' => $enrollment->completed_at?->toIso8601String() ?? now()->toIso8601String(),
                    ],
                    'issued_at' => now(),
                ]
            );

            // Store idempotency key if provided
            if ($idempotencyKey && $skillNft->wasRecentlyCreated) {
                $requestHash = md5(json_encode(['enrollment_id' => $enrollmentId]));
                $responseArray = [
                    'success' => true,
                    'data' => [
                        'enrollment' => $enrollment->fresh()->toArray(),
                        'skill_nft' => $skillNft->toArray(),
                    ],
                    'error' => null,
                ];
                $responseBody = json_encode($responseArray);

                try {
                    IdempotencyKey::on('core')->create([
                        'user_id' => $actorUserId ?? (int) $enrollment->user_id,
                        'scope' => 'training.complete',
                        'key' => $idempotencyKey,
                        'request_hash' => $requestHash,
                        'response_code' => 201,
                        'response_body' => $responseBody,
                        'resource_id' => $skillNft->id,
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Handle race condition
                    if (str_contains($e->getMessage(), '23505') || str_contains($e->getMessage(), '23000')) {
                        $existingIdempotency = IdempotencyKey::on('core')
                            ->where('user_id', $actorUserId ?? (int) $enrollment->user_id)
                            ->where('scope', 'training.complete')
                            ->where('key', $idempotencyKey)
                            ->first();

                        if ($existingIdempotency && $existingIdempotency->request_hash === $requestHash) {
                            $skillNftId = $existingIdempotency->resource_id;
                            $skillNft = SkillNft::on('core')->find($skillNftId);
                            if ($skillNft) {
                                return ['enrollment' => $enrollment->fresh(), 'skill_nft' => $skillNft];
                            }
                        }
                    }
                    throw $e;
                }
            }

            $this->logEvent('enrollment_completed', [
                'enrollment' => $enrollment->toArray(),
                'skill_nft' => $skillNft->toArray(),
            ], $actorUserId, $traceId);

            return ['enrollment' => $enrollment->fresh(), 'skill_nft' => $skillNft];
        });
    }

    /**
     * Get user enrollments
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserEnrollments(int $userId)
    {
        $userUuid = $this->userIdToUuid($userId);
        return Enrollment::on('core')
            ->where('user_id', $userUuid)
            ->with('course')
            ->orderBy('enrolled_at', 'desc')
            ->get();
    }

    /**
     * Get user skill NFTs
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserSkillNfts(int $userId)
    {
        $userUuid = $this->userIdToUuid($userId);
        return SkillNft::on('core')
            ->where('user_id', $userUuid)
            ->with('course')
            ->orderBy('issued_at', 'desc')
            ->get();
    }

    /**
     * Log training event (append-only)
     *
     * @param string $eventType
     * @param array $payload
     * @param int|null $actorUserId
     * @param string|null $traceId
     * @return void
     */
    private function logEvent(
        string $eventType,
        array $payload,
        ?int $actorUserId = null,
        ?string $traceId = null
    ): void {
        $actorUserUuid = $actorUserId ? $this->userIdToUuid($actorUserId) : null;
        $traceUuid = $traceId ?: (string) Str::uuid();

        TrainingEvent::create([
            'event_type' => $eventType,
            'payload' => $payload,
            'actor_user_id' => $actorUserUuid,
            'trace_id' => $traceUuid,
            'created_at' => now(),
        ]);
    }
}
