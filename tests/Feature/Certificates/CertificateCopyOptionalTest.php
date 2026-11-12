<?php

namespace Tests\Feature\Certificates;

use App\VesselCertificate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsRequisitionSchema;
use Tests\TestCase;

/** Certificate Copy is optional when adding a vessel's certificate. */
class CertificateCopyOptionalTest extends TestCase
{
    use BuildsRequisitionSchema;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildRequisitionSchema();

        // The migrations that added these columns use MySQL-only UPDATE..JOIN
        // backfills, so the shape is built by hand under sqlite - only the
        // migration being tested here is run for real.
        Schema::dropIfExists('vessel_certificates');
        Schema::create('vessel_certificates', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vessel_id');
            $t->unsignedBigInteger('category_id');
            $t->string('title');
            $t->string('issue_auth')->nullable();
            $t->date('issue_date');
            $t->date('exp_date')->nullable();
            $t->unsignedInteger('validity_years')->nullable();
            $t->boolean('is_permanent')->default(false);
            $t->string('cert_copy');
            $t->unsignedBigInteger('created_by');
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Schema::dropIfExists('certificate_categories');
        Schema::create('certificate_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('status')->default(true);
            $t->timestamps();
        });
        Artisan::call('migrate', [
            '--path' => database_path('migrations/2026_11_10_100000_make_vessel_certificate_copy_nullable.php'),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->categoryId = DB::table('certificate_categories')->insertGetId(['name' => 'Statutory', 'status' => true]);
    }

    private function payload(): array
    {
        return [
            'Vessel_Name' => $this->vesselId,
            'category_id' => $this->categoryId,
            'title' => 'Load Line',
            'Issue_Date' => '2026-01-01',
            'validity_years' => 5,
        ];
    }

    public function test_certificate_can_be_created_without_a_copy(): void
    {
        $master = $this->makeUser('master', $this->vesselId);

        $this->actingAs($master)->postJson('/certificate/store', $this->payload())->assertOk();

        $this->assertNull(VesselCertificate::where('title', 'Load Line')->value('cert_copy'));
        $this->actingAs($master)->get('/home/certificate')->assertOk()->assertSee('No file');
    }

    public function test_a_supplied_copy_must_still_be_a_pdf(): void
    {
        $this->actingAs($this->makeUser('master', $this->vesselId))
            ->postJson('/certificate/store', $this->payload() + [
                'Certificate_Copy' => UploadedFile::fake()->create('scan.exe', 10),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('Certificate_Copy');
    }
}
