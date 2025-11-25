<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class VisitorsController extends Controller
{
    /**
     * Get paginated visitors (admin only)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
                'gte_date' => 'nullable|date',
                'lte_date' => 'nullable|date',
            ]);

            $perPage = $validated['per_page'] ?? 25;
            $page = $validated['page'] ?? 1;

            $query = Visitor::query();

            if (!empty($validated['gte_date'])) {
                $query->whereDate('created_at', '>=', $validated['gte_date']);
            }

            if (!empty($validated['lte_date'])) {
                $query->whereDate('created_at', '<=', $validated['lte_date']);
            }

            $visitors = $query
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            return response()->json([
                'success' => true,
                'message' => 'Visitors retrieved successfully',
                'data' => $visitors->items(),
                'meta' => [
                    'current_page' => $visitors->currentPage(),
                    'per_page' => $visitors->perPage(),
                    'total' => $visitors->total(),
                    'last_page' => $visitors->lastPage(),
                ]
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving visitors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new visitor record
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'visitor_id' => 'required|string|max:255',
                'ref' => 'nullable|string|max:255',
                'device_type' => 'nullable|string|max:50',
                'browser' => 'nullable|string|max:100',
                'page_visits' => 'nullable|array',
                'page_visits.*.page_name' => 'required_with:page_visits|string|max:255',
                'page_visits.*.section_name' => 'nullable|string|max:255',
                'page_visits.*.in_time' => 'required_with:page_visits|date',
                'page_visits.*.out_time' => 'required_with:page_visits|date|after:page_visits.*.in_time'
            ]);

            // Calculate total_duration for each page visit
            if (isset($validated['page_visits'])) {
                $validated['page_visits'] = $this->calculatePageVisitDurations($validated['page_visits']);
            }

            // Check if visitor already exists
            $existingVisitor = Visitor::findByVisitorId($validated['visitor_id']);
            
            if ($existingVisitor) {
                // Update existing visitor with new session number
                $sessionNumber = Visitor::getLatestSessionNumber($validated['visitor_id']);
                $validated['session'] = $sessionNumber;
                
                // Update the existing visitor
                $existingVisitor->update($validated);
                $visitor = $existingVisitor->fresh();
            } else {
                // Create new visitor with session 1
                $validated['session'] = 1;
                $visitor = Visitor::create($validated);
            }

            $message = $existingVisitor ? 'Visitor session updated successfully' : 'Visitor created successfully';
            $statusCode = $existingVisitor ? 200 : 201;
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $visitor
            ], $statusCode);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating visitor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing visitor record
     */
    public function update(Request $request, string $visitorId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'ref' => 'nullable|string|max:255',
                'device_type' => 'nullable|string|max:50',
                'browser' => 'nullable|string|max:100',
                'page_visits' => 'nullable|array',
                'page_visits.*.page_name' => 'required_with:page_visits|string|max:255',
                'page_visits.*.section_name' => 'nullable|string|max:255',
                'page_visits.*.in_time' => 'required_with:page_visits|date',
                'page_visits.*.out_time' => 'required_with:page_visits|date|after:page_visits.*.in_time'
            ]);

            // Calculate total_duration for each page visit
            if (isset($validated['page_visits'])) {
                $validated['page_visits'] = $this->calculatePageVisitDurations($validated['page_visits']);
            }

            $visitor = Visitor::findByVisitorId($visitorId);
            if (!$visitor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                    'data' => null
                ], 404);
            }

            // If page_visits are provided, append them to existing ones
            if (isset($validated['page_visits'])) {
                $visitor->appendPageVisit($validated['page_visits']);
                unset($validated['page_visits']); // Remove from validated data
            }

            // Update other fields
            $visitor->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Visitor updated successfully',
                'data' => $visitor->fresh()
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating visitor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get visitor information
     */
    public function show(string $visitorId): JsonResponse
    {
        try {
            $visitor = Visitor::findByVisitorId($visitorId);
            
            if (!$visitor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Visitor not found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Visitor retrieved successfully',
                'data' => $visitor
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving visitor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get simple analytics - most visited page, section, and device stats
     */
    public function getAnalytics(): JsonResponse
    {
        try {
            $visitors = Visitor::all();
            $totalVisitors = $visitors->count();

            $sectionStats = [];
            $deviceStats = [];
            $refStats = [];

            foreach ($visitors as $visitor) {
                // Track device type
                if (!empty($visitor->device_type)) {
                    $device = strtolower($visitor->device_type);
                    $deviceStats[$device] = ($deviceStats[$device] ?? 0) + 1;
                }

                // Track referrals
                if (!empty($visitor->ref)) {
                    $ref = strtolower($visitor->ref);
                    $refStats[$ref] = ($refStats[$ref] ?? 0) + 1;
                }

                // Track section stats
                if (is_array($visitor->page_visits)) {
                    foreach ($visitor->page_visits as $visit) {
                        if (!empty($visit['section_name'])) {
                            $sectionName = $visit['section_name'];
                            $sectionStats[$sectionName] = ($sectionStats[$sectionName] ?? 0) + 1;
                        }
                    }
                }
            }

            $mostVisitedSection = $this->getStatSummary($sectionStats, 'none');
            $mostVisitedDevice = $this->getStatSummary($deviceStats, 'unknown');
            $mostCommonRef = $this->getStatSummary($refStats, 'unknown');

            $repeatedVisitors = $visitors
                ->filter(fn ($visitor) => (int) $visitor->session > 1)
                ->map(fn ($visitor) => [
                    'visitor_id' => $visitor->visitor_id,
                    'session' => (int) $visitor->session,
                ])
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_visitors' => $totalVisitors,
                    'most_visited_section' => $mostVisitedSection,
                    'most_visited_device_type' => $mostVisitedDevice,
                    'repeated_visitors' => $repeatedVisitors,
                    'most_common_ref' => $mostCommonRef,
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate total_duration for page visits based on in_time and out_time
     */
    private function calculatePageVisitDurations($pageVisits)
    {
        foreach ($pageVisits as &$visit) {
            if (isset($visit['in_time']) && isset($visit['out_time'])) {
                $inTime = new \DateTime($visit['in_time']);
                $outTime = new \DateTime($visit['out_time']);
                $duration = $outTime->getTimestamp() - $inTime->getTimestamp();
                $visit['total_duration'] = max(0, $duration); // Ensure non-negative duration
            }
        }
        return $pageVisits;
    }

    /**
     * Get the top entry from a stat array with fallback
     */
    private function getStatSummary(array $stats, string $defaultValue): array
    {
        if (empty($stats)) {
            return [
                'value' => $defaultValue,
                'count' => 0,
            ];
        }

        arsort($stats);
        $topValue = array_key_first($stats);

        return [
            'value' => $topValue,
            'count' => $stats[$topValue] ?? 0,
        ];
    }
}

