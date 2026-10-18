<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PmsAccess;
use App\Manufacturer;
use App\PmsComponentCategory;
use App\PmsCondition;
use App\PmsJobType;
use App\PmsLocation;
use App\Rank;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * CRUD for the six PMS lookup lists. They're all the same shape - a name and
 * a couple of extra fields - so one controller and one view drive all of
 * them from the TYPES definition below rather than six near-identical copies.
 */
class PmsMasterDataController extends Controller
{
    use PmsAccess;

    /**
     * Field types: text, number, select (with 'options'), checkbox.
     * 'unique' marks a column with a unique index.
     */
    private const TYPES = [
        'ranks' => [
            'model' => Rank::class,
            'title' => 'Ranks',
            'singular' => 'Rank',
            'icon' => 'fa-id-badge',
            'order' => ['sort_order', 'name'],
            'fields' => [
                'code' => ['label' => 'Code', 'type' => 'text', 'required' => true, 'max' => 10, 'unique' => true, 'upper' => true, 'hint' => 'e.g. CE, 2E, CO'],
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255, 'upper' => true],
                'department' => ['label' => 'Department', 'type' => 'select', 'options' => Rank::DEPARTMENTS],
                'sort_order' => ['label' => 'Sort Order', 'type' => 'number'],
            ],
        ],
        'locations' => [
            'model' => PmsLocation::class,
            'title' => 'Locations',
            'singular' => 'Location',
            'icon' => 'fa-map-marker-alt',
            'order' => ['sort_order', 'name'],
            'usage' => 'components',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255, 'unique' => true, 'upper' => true, 'hint' => 'e.g. ENGINE, DECK'],
                'sort_order' => ['label' => 'Sort Order', 'type' => 'number'],
            ],
        ],
        'categories' => [
            'model' => PmsComponentCategory::class,
            'title' => 'Component Categories',
            'singular' => 'Category',
            'icon' => 'fa-tags',
            'order' => ['name'],
            'usage' => 'components',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255, 'unique' => true],
                'is_critical' => ['label' => 'Critical', 'type' => 'checkbox'],
                'is_class_relevant' => ['label' => 'Class Relevant', 'type' => 'checkbox'],
            ],
            'note' => 'Critical / Class Relevant are what a new component of this category starts with - changing them here doesn\'t alter components that already exist.',
        ],
        'job-types' => [
            'model' => PmsJobType::class,
            'title' => 'Job Types',
            'singular' => 'Job Type',
            'icon' => 'fa-wrench',
            'order' => ['name'],
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255, 'upper' => true, 'hint' => 'e.g. OVERHAUL'],
                'abbreviation' => ['label' => 'Abbreviation', 'type' => 'text', 'required' => true, 'max' => 5, 'upper' => true, 'hint' => 'e.g. O'],
            ],
        ],
        'conditions' => [
            'model' => PmsCondition::class,
            'title' => 'Conditions',
            'singular' => 'Condition',
            'icon' => 'fa-heartbeat',
            'order' => ['id'],
            'usage' => 'components',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255],
                'is_default' => ['label' => 'Default for new components', 'type' => 'checkbox'],
            ],
            'note' => 'Only one condition can be the default - marking another one moves it.',
        ],
        'manufacturers' => [
            'model' => Manufacturer::class,
            'title' => 'Manufacturers',
            'singular' => 'Manufacturer',
            'icon' => 'fa-industry',
            'order' => ['name'],
            'usage' => 'components',
            'fields' => [
                'name' => ['label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255, 'unique' => true],
            ],
        ],
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public static function types(): array
    {
        return array_map(fn ($t) => ['title' => $t['title'], 'icon' => $t['icon']], self::TYPES);
    }

    public function index(string $type)
    {
        $this->authorizePmsMasterData();
        $def = $this->definition($type);

        $query = $def['model']::query();
        if (isset($def['usage'])) {
            $query->withCount($def['usage']);
        }
        foreach ($def['order'] as $column) {
            $query->orderBy($column);
        }

        return view('layouts.pms-master', [
            'type' => $type,
            'def' => $def,
            'rows' => $query->get(),
            'types' => self::types(),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->authorizePmsMasterData();
        $def = $this->definition($type);

        $data = $this->validated($request, $def);

        DB::transaction(function () use ($def, $data) {
            $row = $def['model']::create($data);
            $this->keepSingleDefault($row);
        });

        return response()->json(['message' => $def['singular'].' added.']);
    }

    public function update(Request $request, string $type, int $id)
    {
        $this->authorizePmsMasterData();
        $def = $this->definition($type);
        $row = $def['model']::findOrFail($id);

        $data = $this->validated($request, $def, $row->id);

        DB::transaction(function () use ($row, $data) {
            $row->update($data);
            $this->keepSingleDefault($row);
        });

        return response()->json(['message' => $def['singular'].' updated.']);
    }

    public function destroy(string $type, int $id)
    {
        $this->authorizePmsMasterData();
        $def = $this->definition($type);
        $row = $def['model']::findOrFail($id);

        // The foreign keys are the real guard (a component, or later a job,
        // still pointing at this row) - this just turns the integrity error
        // into something an admin can act on.
        try {
            $row->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This '.strtolower($def['singular']).' is still in use and can\'t be deleted.',
                ], 422);
            }
            throw $e;
        }

        return response()->json(['message' => $def['singular'].' deleted.']);
    }

    private function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function validated(Request $request, array $def, ?int $ignoreId = null): array
    {
        $table = (new $def['model'])->getTable();
        $rules = [];

        foreach ($def['fields'] as $name => $field) {
            $rule = [];
            switch ($field['type']) {
                case 'checkbox':
                    $rule[] = 'boolean';
                    break;
                case 'number':
                    $rule = ['nullable', 'integer', 'min:0', 'max:65535'];
                    break;
                case 'select':
                    $rule = ['nullable', Rule::in($field['options'])];
                    break;
                default:
                    $rule = [($field['required'] ?? false) ? 'required' : 'nullable', 'string', 'max:'.($field['max'] ?? 255)];
            }
            if ($field['unique'] ?? false) {
                $rule[] = Rule::unique($table, $name)->ignore($ignoreId);
            }
            $rules[$name] = $rule;
        }

        // Unchecked checkboxes aren't posted at all, so normalise first.
        foreach ($def['fields'] as $name => $field) {
            if ($field['type'] === 'checkbox') {
                $request->merge([$name => $request->boolean($name)]);
            }
        }

        $data = $request->validate($rules);

        foreach ($def['fields'] as $name => $field) {
            if (is_string($data[$name] ?? null)) {
                $data[$name] = trim($data[$name]);
                if ($field['upper'] ?? false) {
                    $data[$name] = mb_strtoupper($data[$name]);
                }
            }
            if ($field['type'] === 'number') {
                $data[$name] = (int) ($data[$name] ?? 0);
            }
            if ($field['type'] === 'select' && ($data[$name] ?? '') === '') {
                $data[$name] = null;
            }
        }

        return $data;
    }

    /** Conditions only: marking one as the default clears it everywhere else. */
    private function keepSingleDefault($row): void
    {
        if ($row instanceof PmsCondition && $row->is_default) {
            PmsCondition::where('id', '!=', $row->id)->where('is_default', true)->update(['is_default' => false]);
        }
    }
}
