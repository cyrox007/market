<?php

use App\Services\Product\ProductColorImport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('base_colors')) {
            Schema::create('base_colors', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('hex', 7)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('color_solutions')) {
            Schema::create('color_solutions', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('color_solution_base_color')) {
            Schema::create('color_solution_base_color', function (Blueprint $table): void {
                $table->foreignId('color_solution_id')->constrained('color_solutions')->cascadeOnDelete();
                $table->foreignId('base_color_id')->constrained('base_colors')->restrictOnDelete();
                $table->primary(['color_solution_id', 'base_color_id']);
            });
        }

        if (! Schema::hasTable('color_elements')) {
            Schema::create('color_elements', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('filter_name')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('color_elements', 'filter_name')) {
            Schema::table('color_elements', fn (Blueprint $table) => $table->string('filter_name')->nullable()->after('slug'));
        }

        // Строка блока «Цвета товара»: цвет элемента (null — основной цвет) у товара или вариации.
        if (! Schema::hasTable('product_color_rows')) {
            Schema::create('product_color_rows', function (Blueprint $table): void {
                $table->id();
                $table->intOrBigIntBasedOnRelated('product_id', Schema::connection(null), 'products.id');
                $table->foreignId('color_element_id')->nullable()->constrained('color_elements')->restrictOnDelete();
                $table->foreignId('color_solution_id')->constrained('color_solutions')->restrictOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index('product_id');
            });
        }
        // MySQL commits DDL before a later FK failure. Repair an interrupted run.
        if (! collect(Schema::getForeignKeys('product_color_rows'))->contains(fn (array $key): bool => $key['columns'] === ['product_id'])) {
            Schema::table('product_color_rows', fn (Blueprint $table) => $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete());
        }

        $this->seed('base_colors', [
            ['Белый', '#FFFFFF'], ['Чёрный', '#000000'], ['Серый', '#808080'], ['Бежевый', '#D8C3A5'],
            ['Коричневый', '#7B4B2A'], ['Синий', '#1F4E9C'], ['Зелёный', '#2E7D32'], ['Красный', '#C62828'],
            ['Жёлтый', '#F9D71C'], ['Оранжевый', '#EF6C00'], ['Розовый', '#F48FB1'], ['Фиолетовый', '#6A1B9A'],
        ]);
        $this->seed('color_elements', [['Фасад'], ['Столешница'], ['Корпус']]);
        foreach (['Фасад' => 'Цвет фасада', 'Столешница' => 'Цвет столешницы', 'Корпус' => 'Цвет корпуса'] as $name => $filterName) {
            DB::table('color_elements')->where('name', $name)->whereNull('filter_name')->update(['filter_name' => $filterName]);
        }

        $this->transferLegacyColors();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_color_rows');
        Schema::dropIfExists('color_elements');
        Schema::dropIfExists('color_solution_base_color');
        Schema::dropIfExists('color_solutions');
        Schema::dropIfExists('base_colors');
    }

    /** Характеристика «Цвет» → основной цвет товара/вариации; цвет вариативного товара — его вариациям без своего. */
    private function transferLegacyColors(): void
    {
        $attributeId = DB::table('product_attributes')->where('slug', 'color')->value('id');
        if (! $attributeId) {
            return;
        }

        $names = [];
        DB::table('product_product_attributes as ppa')
            ->leftJoin('product_attribute_values as pav', 'pav.id', '=', 'ppa.attribute_value_id')
            ->where('ppa.attribute_id', $attributeId)
            ->orderBy('ppa.product_id')
            ->select('ppa.product_id', 'pav.value', 'ppa.custom_value')
            ->get()
            ->each(function ($row) use (&$names): void {
                $name = trim(preg_replace('/\s+/u', ' ', (string) ($row->value ?? $row->custom_value)));
                if ($name !== '') {
                    $names[(int) $row->product_id][] = $name;
                }
            });
        if ($names === []) {
            return;
        }

        // Из вариантов одного названия выбирается мужской род, затем самый частый.
        $allNames = array_count_values(array_merge(...array_values($names)));
        uksort($allNames, fn (string $a, string $b) => [$this->feminine($a), -$allNames[$a]] <=> [$this->feminine($b), -$allNames[$b]]);
        $canonical = [];
        foreach (array_keys($allNames) as $name) {
            $canonical[ProductColorImport::key($name)] ??= $name;
        }
        $importer = new ProductColorImport;

        $products = DB::table('products')->whereIn('id', array_keys($names))->get(['id', 'parent_product_id', 'is_variable'])->keyBy('id');
        foreach ($names as $productId => $productNames) {
            $product = $products->get($productId);
            if (! $product) {
                continue;
            }
            $receivers = $product->is_variable && ! $product->parent_product_id
                ? DB::table('products')->where('parent_product_id', $productId)->pluck('id')->diff(array_keys($names))->all()
                : [$productId];
            // Несколько значений — один составной цвет с базовыми цветами каждого.
            $parts = array_values(array_unique(array_map(fn (string $name) => $canonical[ProductColorImport::key($name)], $productNames)));
            foreach ($receivers as $receiverId) {
                $importer->setMainColor((int) $receiverId, ProductColorImport::compositeName($parts), $parts, onlyIfEmpty: true);
            }
        }
    }

    private function feminine(string $name): bool
    {
        return (bool) preg_match('/(ая|яя|ое|ее|ые|ие)(?=$|[\s-])/u', mb_strtolower($name));
    }

    private function seed(string $table, array $rows): void
    {
        foreach ($rows as $i => $row) {
            $slug = Str::slug($row[0]);
            if (DB::table($table)->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table($table)->insert(array_filter([
                'name' => $row[0],
                'slug' => $slug,
                'hex' => $row[1] ?? null,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($value) => $value !== null));
        }
    }
};
