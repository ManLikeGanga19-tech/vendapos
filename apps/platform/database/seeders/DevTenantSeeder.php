<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalogue\Models\Category;
use App\Domain\Catalogue\Models\Item;
use App\Domain\Tenancy\Actions\EnrolDevice;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;
use VendaPOS\Core\Enums\BusinessType;
use VendaPOS\Core\Enums\ItemType;
use VendaPOS\Core\Enums\VatCategory;

/**
 * The local development tenant, Danamo Restaurant: one branch, a small menu
 * and an enrolled till. Run with `php artisan db:seed --class=DevTenantSeeder`;
 * safe to rerun, and refuses to run in production.
 */
final class DevTenantSeeder extends Seeder
{
    public const string TENANT_ID = 'danamo';

    public function run(EnrolDevice $enrol): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DevTenantSeeder must never run in production.');
        }

        if (Tenant::find(self::TENANT_ID) !== null) {
            $this->command->info('Danamo Restaurant already exists; nothing to do.');

            return;
        }

        /** @var Tenant $tenant */
        $tenant = Tenant::create(['id' => self::TENANT_ID, 'name' => 'Danamo Restaurant', 'modules' => [BusinessType::Restaurant->value]]);
        $tenant->domains()->create(['domain' => 'danamo.localhost']);

        $branch = $tenant->run(function () {
            $branch = Branch::create(['name' => 'Danamo Restaurant', 'business_type' => BusinessType::Restaurant]);

            $menu = [
                'Grill' => [['Nyama choma (1 kg)', 185_000], ['Kuku choma (half)', 95_000]],
                'Mains' => [['Pilau beef', 65_000], ['Ugali sukuma', 30_000], ['Chapati', 5_000]],
                'Drinks' => [['Soda 500 ml', 10_000], ['Fresh passion juice', 20_000], ['Chai', 8_000]],
            ];

            foreach (array_keys($menu) as $position => $categoryName) {
                $category = Category::create(['name' => $categoryName, 'sort_order' => $position]);

                foreach ($menu[$categoryName] as [$name, $priceCents]) {
                    Item::create([
                        'category_id' => $category->id,
                        'type' => ItemType::Product,
                        'name' => $name,
                        'price_cents' => $priceCents,
                        'vat_category' => VatCategory::Standard,
                    ]);
                }
            }

            return $branch;
        });

        $enrolment = $enrol->handle($tenant, $branch->id, 'Till 1');

        $this->command->info("Danamo Restaurant created (tenant '{$tenant->id}', branch {$branch->id}).");
        $this->command->info("Till 1 device ID: {$enrolment->device->id}");
        $this->command->warn("Till 1 token (shown once): {$enrolment->plainTextToken}");
    }
}
