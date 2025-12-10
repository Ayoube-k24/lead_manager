<?php

namespace Database\Seeders;

use App\LeadStatus as LeadStatusEnum;
use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

class LeadStatusSeeder extends Seeder
{
    /**
     * Map Tailwind color classes to hex colors.
     */
    private function getColorFromClass(string $class): string
    {
        // Extract color from Tailwind class like "bg-yellow-100"
        $colorMap = [
            'yellow' => '#FCD34D', // yellow-400
            'blue' => '#60A5FA', // blue-400
            'orange' => '#FB923C', // orange-400
            'green' => '#4ADE80', // green-400
            'red' => '#F87171', // red-400
            'purple' => '#A78BFA', // purple-400
            'gray' => '#9CA3AF', // gray-400
            'amber' => '#FBBF24', // amber-400
            'emerald' => '#34D399', // emerald-400
            'indigo' => '#818CF8', // indigo-400
            'teal' => '#2DD4BF', // teal-400
            'cyan' => '#22D3EE', // cyan-400
            'slate' => '#94A3B8', // slate-400
        ];

        foreach ($colorMap as $color => $hex) {
            if (str_contains($class, $color)) {
                return $hex;
            }
        }

        return '#6B7280'; // Default gray
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'enum' => LeadStatusEnum::PendingEmail,
                'order' => 1,
            ],
            [
                'enum' => LeadStatusEnum::EmailConfirmed,
                'order' => 2,
            ],
            [
                'enum' => LeadStatusEnum::PendingCall,
                'order' => 3,
            ],
            [
                'enum' => LeadStatusEnum::NoAnswer,
                'order' => 4,
            ],
            [
                'enum' => LeadStatusEnum::Busy,
                'order' => 5,
            ],
            [
                'enum' => LeadStatusEnum::WrongNumber,
                'order' => 6,
            ],
            [
                'enum' => LeadStatusEnum::NotInterested,
                'order' => 7,
            ],
            [
                'enum' => LeadStatusEnum::CallbackPending,
                'order' => 8,
            ],
            [
                'enum' => LeadStatusEnum::QuoteSent,
                'order' => 9,
            ],
        ];

        foreach ($statuses as $statusData) {
            $enum = $statusData['enum'];
            $colorClass = $enum->colorClass();
            $color = $this->getColorFromClass($colorClass);

            LeadStatus::updateOrCreate(
                ['slug' => $enum->value],
                [
                    'name' => $enum->label(),
                    'color' => $color,
                    'description' => $enum->description(),
                    'is_system' => true,
                    'is_active' => $enum->isActive(),
                    'is_final' => $enum->isFinal(),
                    'can_be_set_after_call' => $enum->canBeSetAfterCall(),
                    'order' => $statusData['order'],
                ]
            );
        }
    }
}
