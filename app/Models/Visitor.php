<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'visitor_id',
        'ref',
        'device_type',
        'browser',
        'session',
        'page_visits',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_visits' => 'array',
            'session' => 'integer',
        ];
    }

    /**
     * Find a visitor by visitor_id
     *
     * @param string $visitorId
     * @return Visitor|null
     */
    public static function findByVisitorId(string $visitorId): ?Visitor
    {
        // Allow lookup by primary key when numeric IDs are passed
        if (ctype_digit($visitorId)) {
            $byPrimaryId = static::find((int) $visitorId);
            if ($byPrimaryId) {
                return $byPrimaryId;
            }
        }

        return static::where('visitor_id', $visitorId)->first();
    }

    /**
     * Get the latest session number for a visitor_id
     *
     * @param string $visitorId
     * @return int
     */
    public static function getLatestSessionNumber(string $visitorId): int
    {
        $latestSession = static::where('visitor_id', $visitorId)
            ->max('session');
        
        return $latestSession ? $latestSession + 1 : 1;
    }

    /**
     * Append page visits to existing page_visits array
     *
     * @param array $newPageVisits
     * @return void
     */
    public function appendPageVisit(array $newPageVisits): void
    {
        $existingVisits = $this->page_visits ?? [];
        
        // Merge new visits with existing ones
        $allVisits = array_merge($existingVisits, $newPageVisits);
        
        $this->page_visits = $allVisits;
        $this->save();
    }
}

