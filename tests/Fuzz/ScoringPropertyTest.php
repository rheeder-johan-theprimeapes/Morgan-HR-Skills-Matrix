<?php

declare(strict_types=1);

use App\Services\ScoreCalculator;
use Eris\Generators;

it('keeps generated scores bounded deterministic order independent and monotonic', function (): void {
    $this->limitTo(100)->forAll(Generators::choose(0, 99), Generators::choose(0, 100), Generators::choose(1, 10000), Generators::choose(1, 10000))->then(function (int $first, int $second, int $weightA, int $weightB): void {
        $components = [];
        $inputs = [];
        foreach ([1 => [$first, $weightA], 2 => [$second, $weightB]] as $id => [$ordinal, $weight]) {
            $levels = [];
            for ($level = 0; $level <= 100; $level++) {
                $levels[] = ['level_id' => $id * 1000 + $level, 'ordinal' => $level, 'label' => 'Illustrative '.$level, 'points' => $level * 100];
            }
            $components[] = ['group_id' => $id, 'name' => 'Component '.$id, 'weight' => $weight, 'skills' => [['skill_id' => $id, 'name' => 'Skill '.$id, 'allows_na' => true, 'required' => true, 'requires_evidence' => false, 'department_ids' => [], 'job_role_ids' => [], 'levels' => $levels]]];
            $inputs[] = ['skill_id' => $id, 'applicable' => true, 'state' => 'rated', 'level_id' => $id * 1000 + $ordinal, 'revision_id' => $id, 'evidence_complete' => true, 'na_reason_present' => true];
        }
        $config = ['components' => $components, 'bands' => [['label' => 'All scores', 'from' => 0, 'to' => 10000]]];
        $calculator = new ScoreCalculator;
        $result = $calculator->calculate($config, $inputs);
        expect($result['score'])->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        expect($calculator->calculate($config, $inputs))->toBe($result);
        $config['components'] = array_reverse($components);
        foreach ($config['components'] as &$component) {
            $component['skills'] = array_reverse($component['skills']);
            $component['skills'][0]['levels'] = array_reverse($component['skills'][0]['levels']);
        } unset($component);
        expect($calculator->calculate($config, array_reverse($inputs)))->toBe($result);
        $inputs[0]['level_id']++;
        expect($calculator->calculate($config, $inputs)['score'])->toBeGreaterThanOrEqual($result['score']);
    });
})->group('fuzz');
