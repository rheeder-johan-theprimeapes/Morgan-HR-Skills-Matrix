<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * @phpstan-import-type FrameworkConfig from FrameworkDefinition
 *
 * @phpstan-type ScoreInput array{skill_id:int, applicable:bool, state:string, level_id:?int, revision_id:?int, evidence_complete:bool, na_reason_present:bool, applicability_source?:string,blocking_reason?:string}
 * @phpstan-type ScoreCell array{skill_id:int,name:string,state:string,revision_id:?int,level_id:?int,points:?float,evidence_complete:bool,applicability_source:string}
 * @phpstan-type ScoreComponent array{group_id:int,name:string,weight:int,denominator:int,mean:?float,effective_weight:float,skills:list<ScoreCell>}
 * @phpstan-type ScoreResult array{score:?float,band:?string,complete:bool,blocking_reasons:list<string>,counts:array{rated:int,missing:int,na:int,inapplicable:int,evidence_missing:int,invalid:int},included_weight:int,components:list<ScoreComponent>}
 */
class ScoreCalculator
{
    /**
     * @param  FrameworkConfig  $definition
     * @param  list<ScoreInput>  $inputs
     * @return ScoreResult
     */
    public function calculate(array $definition, array $inputs): array
    {
        $bySkill = [];
        foreach ($inputs as $input) {
            if (isset($bySkill[$input['skill_id']])) {
                throw new InvalidArgumentException('A skill has more than one scoring input.');
            }
            $bySkill[$input['skill_id']] = $input;
        }
        $definitions = $definition['components'];
        usort($definitions, fn (array $a, array $b): int => $a['group_id'] <=> $b['group_id']);
        $counts = ['rated' => 0, 'missing' => 0, 'na' => 0, 'inapplicable' => 0, 'evidence_missing' => 0, 'invalid' => 0];
        $blocking = [];
        $components = [];
        $weighted = 0.0;
        $includedWeight = 0;
        foreach ($definitions as $component) {
            if ($component['weight'] <= 0) {
                throw new InvalidArgumentException('Published component weights must be positive.');
            }
            $skills = $component['skills'];
            usort($skills, fn (array $a, array $b): int => $a['skill_id'] <=> $b['skill_id']);
            $cells = [];
            $sum = 0;
            $denominator = 0;
            foreach ($skills as $skill) {
                $input = $bySkill[$skill['skill_id']] ?? ['skill_id' => $skill['skill_id'], 'applicable' => true, 'state' => 'unassessed', 'level_id' => null, 'revision_id' => null, 'evidence_complete' => false, 'na_reason_present' => false];
                $state = $input['state'];
                if (($input['blocking_reason'] ?? '') !== '') {
                    $blocking[] = $input['blocking_reason'];
                }
                $points = null;
                if (! $input['applicable']) {
                    $state = 'inapplicable';
                    $counts['inapplicable']++;
                } elseif ($state === 'not_applicable') {
                    $counts['na']++;
                    if (! $skill['allows_na'] || ! $input['na_reason_present']) {
                        $blocking[] = $skill['name'].': N/A needs permission and a reason.';
                    }
                } elseif ($state === 'unassessed') {
                    $counts['missing']++;
                    if ($skill['required']) {
                        $blocking[] = $skill['name'].': required manager rating is missing.';
                    }
                } elseif ($state === 'rated') {
                    $matched = null;
                    foreach ($skill['levels'] as $level) {
                        if ($level['level_id'] === $input['level_id']) {
                            $matched = $level;
                            break;
                        }
                    }
                    if ($matched === null) {
                        $state = 'invalid';
                        $counts['invalid']++;
                        $blocking[] = $skill['name'].': rating is absent from this framework version.';
                    } else {
                        $points = $matched['points'] / 100.0;
                        $sum += $matched['points'];
                        $denominator++;
                        $counts['rated']++;
                        if ($skill['requires_evidence'] && ! $input['evidence_complete']) {
                            $counts['evidence_missing']++;
                            $blocking[] = $skill['name'].': required evidence is missing.';
                        }
                    }
                } else {
                    $state = 'invalid';
                    $counts['invalid']++;
                    $blocking[] = $skill['name'].': unsupported assessment state.';
                }
                $cells[] = ['skill_id' => $skill['skill_id'], 'name' => $skill['name'], 'state' => $state, 'revision_id' => $input['revision_id'], 'level_id' => $input['level_id'], 'points' => $points, 'evidence_complete' => $input['evidence_complete'], 'applicability_source' => $input['applicability_source'] ?? 'Published applicability'];
            }
            $mean = $denominator === 0 ? null : $sum / $denominator;
            if ($mean !== null) {
                $weighted += $mean * $component['weight'];
                $includedWeight += $component['weight'];
            }
            $components[] = ['group_id' => $component['group_id'], 'name' => $component['name'], 'weight' => $component['weight'], 'denominator' => $denominator, 'mean' => $mean === null ? null : $mean / 100.0, 'effective_weight' => 0.0, 'skills' => $cells];
        }
        $score = $includedWeight === 0 ? null : round($weighted / $includedWeight, 0, PHP_ROUND_HALF_UP) / 100;
        if ($score === null) {
            $blocking[] = 'No eligible rated inputs remain; a final score cannot be calculated.';
        }
        foreach ($components as &$component) {
            $component['effective_weight'] = $includedWeight === 0 || $component['denominator'] === 0 ? 0.0 : $component['weight'] * 1.0 / $includedWeight;
        }
        unset($component);
        $bandName = null;
        if ($score !== null) {
            $scoreHundredths = (int) round($score * 100);
            foreach ($definition['bands'] as $band) {
                if ($scoreHundredths >= $band['from'] && ($scoreHundredths < $band['to'] || ($band['to'] === 10000 && $scoreHundredths === 10000))) {
                    $bandName = $band['label'];
                    break;
                }
            }
            if ($bandName === null) {
                $blocking[] = 'The score has no valid band in this framework.';
            }
        }

        return ['score' => $score, 'band' => $bandName, 'complete' => $blocking === [], 'blocking_reasons' => $blocking, 'counts' => $counts, 'included_weight' => $includedWeight, 'components' => $components];
    }
}
