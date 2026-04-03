<?php

use App\Models\Adjustment;
use App\Models\CheckInDetail;
use App\Models\ConfigurationRule;
use App\Models\Employee\Employee;
use App\Models\Leave;
use App\Models\Payroll;
use App\Models\PaySplit;
use Carbon\Carbon;
use function App\Services\calculateSeniorityBonus;

public static function calculatePayrollForAllEmployees($payrollId)
{
    $payroll = Payroll::find($payrollId);

    if (!$payroll) return [];

    $employees = Employee::where('project_id', $payroll->project_id)
        ->where('status', 'active')
        ->get();

    $checkIn = $payroll->checkIn;

    if (!$checkIn) {

        return response(
            [
                'message' => 'No check-in found for the payroll period.',
                'status' => 404
            ], 404
        );
    }

    $results = [];

    $types = [
        [
            'type' => 'paid_leave',
            'label' => 'Congé payé',
            'nature' => 'addition',
            'code' => 'G-100',
            'summable' => true,
        ],
        [
            'type' => 'sick_leave',
            'label' => 'Absence maladie',
            'nature' => 'addition',
            'code' => 'G-101',
            'summable' => true,
        ],
        [
            'type' => 'overtime',
            'label' => 'Heures supplémentaires',
            'nature' => 'addition',
            'code' => 'G-102',
            'summable' => true,
        ],
        [
            'type' => 'authorized_absence',
            'label' => 'Absence autorisée',
            'nature' => 'addition',
            'code' => 'G-103',
            'summable' => true,
        ],
        [
            'type' => 'public_holidays',
            'label' => 'Jours fériés',
            'nature' => 'addition',
            'code' => 'G-104',
            'summable' => true,
        ],
        [
            'type' => 'technical_unemployment',
            'label' => 'Chômage technique',
            'nature' => 'addition',
            'code' => 'G-105',
            'summable' => true,
        ],
        /*[
            'type' => 'unjustified_absence',
            'label' => 'Absence injustifiée',
            'nature' => 'deduction',
            'code' => 'R-100',
            'summable' => false,
        ],*/
        /*[
            'type' => 'suspension',
            'label' => 'Mise à pied',
            'nature' => 'deduction',
            'code' => 'R-101',
            'summable' => false,
        ],
        [
            'type' => 'unpaid_leave',
            'label' => 'Congé sans solde',
            'nature' => 'deduction',
            'code' => 'R-102',
            'summable' => false,
        ]*/
    ];

    foreach ($employees as $employee) {

        $results[$employee->id] = [];

        $payrollDetail = $payroll->details()
            ->where('employee_id', $employee->id)
            ->first();

        $deductions = 0;

        $additions = 0;

        if ($payrollDetail) {
            $adjustments = Adjustment::where('payroll_detail_id', $payrollDetail->id)->get();
            if ($adjustments->count() > 0) {
                foreach ($adjustments as $adjustment) {
                    if ($adjustment->type == 'deduction') {
                        $deductions += $adjustment->amount;
                        $results[$employee->id][] = [
                            'type' => 'deduction',
                            'label' => $adjustment->name,
                            'hour_rate' => $adjustment->amount,
                            'qte' => $adjustment->qte ?? 1,
                            'total' => -$adjustment->amount * $adjustment->qte,
                        ];
                    } else {
                        $additions += $adjustment->amount;
                        $results[$employee->id][] = [
                            'type' => 'addition',
                            'label' => $adjustment->name,
                            'hour_rate' => $adjustment->amount,
                            'qte' => $adjustment->quantity ?? 1,
                            'total' => $adjustment->amount * $adjustment->quantity ?? 1,
                        ];
                    }
                }
            }
        }

        $baseComponentId = $employee->project->components()
            ->wherePivot('is_base_salary', true)
            ->wherePivot('active', true)
            ->first();

        $baseComponent = $employee->salary->components->first(function ($component) use ($baseComponentId) {
            return $component->id === $baseComponentId?->id;
        });

        $baseAmount = $baseComponent?->pivot->amount ?? 0;

        $seniorityBonus = calculateSeniorityBonus(
            $employee->hire_date,
            $baseAmount
        );

        $checkInDetail = CheckInDetail::where('check_in_id', $checkIn->id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$checkInDetail) {
            continue;
        }

        $payConfig = ConfigurationRule::where('employee_id', $employee->id)
            ->where('project_id', $payroll->project_id)
            ->first();

        if ($payConfig) {
            continue;
        }

        $scheduledHours = $checkInDetail->scheduled_hours ?? 0;

        foreach ($types as $type) {
            $qte = $checkInDetail->{$type['type']} ?? 0;

            // Force qte = 0.5 for public holidays
            if ($type['type'] === 'public_holidays' && $qte > 0) {
                $qte = 0.5;
            }

            $typeValue = self::getPayrollValueForType($employee, $payroll->project_id, $type['type']);
            $hourRate = ($scheduledHours > 0) ? ($typeValue / $scheduledHours) : 0;

            $results[$employee->id][] = [
                'type' => $type['type'],
                'label' => $type['label'],
                'hour_rate' => round($hourRate, 2),
                'qte' => round($qte, 2),
                'total' => $type['nature'] === 'addition'
                    ? round($hourRate * $qte, 2)
                    : -round($hourRate * $qte, 2),
            ];
        }


        foreach ($employee->salary->components as $component) {
            $amount = $component->pivot->amount / $scheduledHours;
            $qte = $checkInDetail->worked_hours;
            $totalAmount = ($component->pivot->amount / $scheduledHours) * $checkInDetail->worked_hours;
            $results[$employee->id][] = [
                'type' => 'addition',
                'label' => $component->name,
                'code' => $component->code,
                'hour_rate' => round($amount, 2),
                'qte' => round($qte, 2),
                'total' => round($totalAmount, 2),
            ];
        }

        // filter the results to remove zero total and sum the total salary
        $results[$employee->id] = array_filter($results[$employee->id], function ($result) {
            return isset($result['total']) && $result['total'] != 0;
        });

        $totalSalary = 0;

        foreach ($results[$employee->id] as $result) {
            $totalSalary += $result['total'];
        }

        if ($seniorityBonus['total_amount'] > 0) {
            $totalSalary += $seniorityBonus['total_amount'];
            $results[$employee->id][] = [
                'type' => 'addition',
                'label' => 'Prime d\'ancienneté',
                'code' => 'G-100',
                'hour_rate' => round($seniorityBonus['amount'], 2),
                'qte' => $seniorityBonus['qte'],
                'total' => $seniorityBonus['total_amount'],
            ];
        }

        $results[$employee->id]['total_salary'] = round($totalSalary, 2);

        $salary = calculate_brut_from_net(
            $totalSalary,
            $employee->has_cnss,
            $employee->has_cnam,
            $employee->has_its
        );

        $results[$employee->id] = array_filter($results[$employee->id], function ($result) {
            return isset($result['total']) && $result['total'] != 0;
        });

        // Add salary details to results to the payroll details
        $payrollDetail = $payroll->details()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'payroll_id' => $payrollId,
            ],
            [
                'brut_salary' => $salary['brut'],
                'net_salary' => $salary['net'],
                'gross_salary' => $salary['masse_salaire'],
                'cnss' => $salary['cnss'],
                'cnss_p' => $salary['cnss_p'],
                'cnam' => $salary['cnam'],
                'cnam_p' => $salary['cnam_p'],
                'its' => $salary['its'],
                'apprenticeship_tax' => $salary['taxe_app'],
                'total_deductions' => $salary['cnss'] + $salary['cnam'] + $salary['its'],
                'total_allowances' => $salary['cnss_p'] + $salary['cnam_p'],
            ]);


        $existingSplit = PaySplit::where('payroll_detail_id', $payrollDetail->id)
//                ->where('type', $type)
            ->first();

        if (!$existingSplit) {
            $paySplit = PaySplit::create([
                'payroll_detail_id' => $payrollDetail->id,
//                    'type' => $type,
                'type' => 'net_only',
                'start_date' => $payroll->start_date,
                'end_date' => $payroll->end_date,
                'employee_id' => $employee->id,
            ]);
        } else {
            $existingSplit->update([
                'start_date' => $payroll->start_date,
                'end_date' => $payroll->end_date,
                'employee_id' => $employee->id,
                'payroll_detail_id' => $payrollDetail->id,
                'type' => 'net_only',
            ]);
            $existingSplit->details()->delete();
            $paySplit = $existingSplit;
        }

        $paySplit->details()->createMany(
            array_filter(array_map(function ($result) {
                return [
                    'component' => $result['label'],
                    'qte' => $result['qte'],
                    'amount' => $result['hour_rate'],
                    'total_amount' => $result['total'],
                    'type' => /*$result['type'] ?? */'addition',
                    'code' => $result['code'] ?? null,
                ];
            }, $results[$employee->id]))
        );

        // Leave balance management for paid leave
        $currentMonth = Carbon::parse($payroll->end_date)->format('Y-m-d');

        Leave::where('employee_id', $employee->id)
            ->where('date', $currentMonth)
            ->delete();

        $latestLeave = Leave::where('employee_id', $employee->id)
            ->where('date', '<', $currentMonth)
            ->orderBy('date', 'desc')
            ->first();

        if (!$latestLeave) {
            Leave::create([
                'employee_id'     => $employee->id,
                'date'            => $payroll->end_date,
                'brut_salary'     => $salary['brut'],
                'net_salary'      => $salary['net'],
                'acquired_days'   => 2,
                'acquired_mru'    => ( $salary['net'] / 12),
                'used_days'       => $checkInDetail->paid_leave,
                'used_mru'        => $checkInDetail->paid_leave * ($salary['net'] / 12),
                'remaining_days'  => 2 - $checkInDetail->paid_leave,
                'remaining_mru'   => ($salary['net'] / 12)- ($checkInDetail->paid_leave * ($salary['net'] / 12))
            ]);
        } else {
            $newUsedDays      = $checkInDetail->paid_leave;
            $hourRate         = $latestLeave->remaining_days > 0
                ? $latestLeave->remaining_mru / $latestLeave->remaining_days
                : 0;
            $newUsedMru       = $newUsedDays * $hourRate;
            $newRemainingDays = $latestLeave->remaining_days + 2 - $newUsedDays;
            $newRemainingMru  = $latestLeave->remaining_mru + ($salary['net'] / 12) - $newUsedMru;

            Leave::create([
                'employee_id'     => $employee->id,
                'date'            => $payroll->end_date,
                'brut_salary'     => $salary['brut'],
                'net_salary'      => $salary['net'],
                'acquired_days'   => $latestLeave->acquired_days + 2,
                'acquired_mru'    => $latestLeave->acquired_mru + ( $salary['net'] / 12),
                'used_days'       => $newUsedDays,
                'used_mru'        => $newUsedMru,
                'remaining_days'  => $newRemainingDays,
                'remaining_mru'   => $newRemainingMru,
            ]);
        }
    }

    //return $results;

    return response(
        [
            'message' => 'Payroll calculated successfully.',
            'status' => 200,
        ],
        200
    );
}
