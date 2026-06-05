<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SeoLocation;

class SeoLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // LAGOS
            ['type' => 'city', 'name' => 'Lagos', 'slug' => 'lagos', 'state' => 'Lagos State', 'tier' => 1,
             'description' => 'Lagos is Nigeria\'s commercial capital and largest city, home to over 15 million people. The demand for domestic staff is the highest in the country.',
             'demand_context' => 'With its fast-paced lifestyle, large expatriate community, and growing middle class, Lagos families constantly seek reliable domestic help.',
             'latitude' => 6.5244, 'longitude' => 3.3792, 'household_estimate' => 3000000],

            ['type' => 'area', 'name' => 'Victoria Island', 'slug' => 'victoria-island', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Victoria Island is Lagos\' premier business and residential district, known for luxury apartments and high-net-worth families.',
             'notable_estates' => ['Akin Adesola', 'Admiralty Way', 'Tiamiyu Savage'], 'latitude' => 6.4281, 'longitude' => 3.4219],
            ['type' => 'area', 'name' => 'Ikoyi', 'slug' => 'ikoyi', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Ikoyi is one of Lagos\' most affluent neighbourhoods, popular with diplomats and business executives.',
             'notable_estates' => ['Bourdillon', 'Awolowo Road', 'Kingsway Road'], 'latitude' => 6.4530, 'longitude' => 3.4316],
            ['type' => 'area', 'name' => 'Lekki Phase 1', 'slug' => 'lekki-phase-1', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Lekki Phase 1 is a well-planned residential area with estates, gated communities, and a growing middle-to-upper-class population.',
             'notable_estates' => ['Chevy View Estate', 'Irish Town', 'Lekki Phase 1 Estate'], 'latitude' => 6.4474, 'longitude' => 3.4705],
            ['type' => 'area', 'name' => 'Lekki Phase 2', 'slug' => 'lekki-phase-2', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Lekki Phase 2 is a rapidly developing residential extension with new estates and housing developments.',
             'notable_estates' => ['VGC Road', 'Ikate Elegushi', 'Lekki Gardens'], 'latitude' => 6.4550, 'longitude' => 3.5100],
            ['type' => 'area', 'name' => 'Chevron', 'slug' => 'chevron', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Chevron Drive area in Lekki is a busy commercial and residential corridor with many family homes.',
             'notable_estates' => ['Chevron Drive', 'Lekki County Home'], 'latitude' => 6.4400, 'longitude' => 3.4900],
            ['type' => 'area', 'name' => 'Ajah', 'slug' => 'ajah', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Ajah is a fast-growing residential suburb along the Lekki-Epe Expressway with many young families.',
             'notable_estates' => ['Abraham Adesanya', 'Ilaje', 'Awoyaya Road'], 'latitude' => 6.4667, 'longitude' => 3.5667],
            ['type' => 'area', 'name' => 'Sangotedo', 'slug' => 'sangotedo', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Sangotedo is an emerging residential area with new estates and the Novare Mall (Shoprite).',
             'notable_estates' => ['Shoprite Road', 'Amen Estate'], 'latitude' => 6.4700, 'longitude' => 3.6000],
            ['type' => 'area', 'name' => 'Ikeja GRA', 'slug' => 'ikeja-gra', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Ikeja GRA is the upscale government reservation area in Lagos\' capital district.',
             'notable_estates' => ['Allen Avenue', 'Opebi Road', 'Isaac John Street'], 'latitude' => 6.5964, 'longitude' => 3.3518],
            ['type' => 'area', 'name' => 'Maryland', 'slug' => 'maryland', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Maryland is a well-established residential and commercial area on the Lagos mainland.',
             'latitude' => 6.5795, 'longitude' => 3.3666],
            ['type' => 'area', 'name' => 'Magodo', 'slug' => 'magodo', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Magodo is a gated residential estate community on the mainland popular with families.',
             'notable_estates' => ['Magodo Phase 1', 'Magodo Phase 2', 'GRA Phase 2'], 'latitude' => 6.5833, 'longitude' => 3.3833],
            ['type' => 'area', 'name' => 'Surulere', 'slug' => 'surulere', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Surulere is a vibrant mainland neighbourhood known for its residential density and entertainment venues.',
             'latitude' => 6.4969, 'longitude' => 3.3538],
            ['type' => 'area', 'name' => 'Yaba', 'slug' => 'yaba', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Yaba is Lagos\' tech hub and home to the University of Lagos, with a mix of students and young professionals.',
             'latitude' => 6.5079, 'longitude' => 3.3781],
            ['type' => 'area', 'name' => 'Gbagada', 'slug' => 'gbagada', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Gbagada is a popular mainland residential area with a growing middle class.',
             'latitude' => 6.5475, 'longitude' => 3.3838],
            ['type' => 'area', 'name' => 'Festac', 'slug' => 'festac', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Festac Town is a large planned residential estate on the Lagos mainland.',
             'latitude' => 6.4636, 'longitude' => 3.2806],
            ['type' => 'area', 'name' => 'Apapa', 'slug' => 'apapa', 'state' => 'Lagos State', 'tier' => 1, 'parent_slug' => 'lagos',
             'description' => 'Apapa is the industrial and port area of Lagos with significant expatriate and business presence.',
             'latitude' => 6.4474, 'longitude' => 3.3594],

            // ABUJA
            ['type' => 'city', 'name' => 'Abuja', 'slug' => 'abuja', 'state' => 'FCT', 'tier' => 1,
             'description' => 'Abuja is Nigeria\'s purpose-built capital city, home to government officials, diplomats, and business leaders.',
             'demand_context' => 'As the seat of government with a high concentration of wealthy families and expatriates, Abuja has strong demand for premium domestic staff.',
             'latitude' => 9.0579, 'longitude' => 7.4951, 'household_estimate' => 800000],

            ['type' => 'area', 'name' => 'Maitama', 'slug' => 'maitama', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Maitama is Abuja\'s most exclusive residential district, home to ministers, ambassadors, and business tycoons.',
             'notable_estates' => ['Aguiyi Ironsi Street', 'Maitama District'], 'latitude' => 9.0833, 'longitude' => 7.4833],
            ['type' => 'area', 'name' => 'Asokoro', 'slug' => 'asokoro', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Asokoro is a high-end diplomatic residential area, home to the Presidential Villa.',
             'latitude' => 9.0420, 'longitude' => 7.5200],
            ['type' => 'area', 'name' => 'Wuse 2', 'slug' => 'wuse-2', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Wuse 2 is Abuja\'s entertainment and commercial district with upscale apartments and restaurants.',
             'latitude' => 9.0700, 'longitude' => 7.4700],
            ['type' => 'area', 'name' => 'Garki', 'slug' => 'garki', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Garki is a well-established residential and commercial district in central Abuja.',
             'latitude' => 9.0333, 'longitude' => 7.4833],
            ['type' => 'area', 'name' => 'Jabi', 'slug' => 'jabi', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Jabi is a modern residential area near Jabi Lake Mall with many young families.',
             'latitude' => 9.0667, 'longitude' => 7.4333],
            ['type' => 'area', 'name' => 'Gwarinpa', 'slug' => 'gwarinpa', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Gwarinpa is Abuja\'s largest residential estate, housing a large middle-class population.',
             'latitude' => 9.1167, 'longitude' => 7.4167],
            ['type' => 'area', 'name' => 'Lokogoma', 'slug' => 'lokogoma', 'state' => 'FCT', 'tier' => 1, 'parent_slug' => 'abuja',
             'description' => 'Lokogoma is a rapidly developing residential district on the outskirts of Abuja.',
             'latitude' => 8.9833, 'longitude' => 7.5167],

            // PORT HARCOURT
            ['type' => 'city', 'name' => 'Port Harcourt', 'slug' => 'port-harcourt', 'state' => 'Rivers State', 'tier' => 1,
             'description' => 'Port Harcourt is the capital of Rivers State and Nigeria\'s oil industry hub, with a wealthy expatriate and business community.',
             'demand_context' => 'The oil industry wealth and large expatriate community in Port Harcourt create strong demand for quality domestic staff.',
             'latitude' => 4.8156, 'longitude' => 7.0498, 'household_estimate' => 500000],

            ['type' => 'area', 'name' => 'GRA', 'slug' => 'gra', 'state' => 'Rivers State', 'tier' => 1, 'parent_slug' => 'port-harcourt',
             'description' => 'Port Harcourt GRA is the city\'s most prestigious residential area with large homes and expatriate compounds.',
             'notable_estates' => ['Old GRA', 'New GRA'], 'latitude' => 4.8100, 'longitude' => 7.0100],
            ['type' => 'area', 'name' => 'Woji', 'slug' => 'woji', 'state' => 'Rivers State', 'tier' => 1, 'parent_slug' => 'port-harcourt',
             'description' => 'Woji is an upscale residential area in Port Harcourt popular with oil industry professionals.',
             'latitude' => 4.8300, 'longitude' => 7.0300],
            ['type' => 'area', 'name' => 'Rumuola', 'slug' => 'rumuola', 'state' => 'Rivers State', 'tier' => 1, 'parent_slug' => 'port-harcourt',
             'description' => 'Rumuola is a well-established residential neighbourhood with a mix of middle and upper-class families.',
             'latitude' => 4.8500, 'longitude' => 7.0200],

            // TIER 2 CITIES
            ['type' => 'city', 'name' => 'Ibadan', 'slug' => 'ibadan', 'state' => 'Oyo State', 'tier' => 2,
             'description' => 'Ibadan is the capital of Oyo State and one of Nigeria\'s largest cities, known for its rich history and growing economy.',
             'latitude' => 7.3775, 'longitude' => 3.9470, 'household_estimate' => 600000],

            ['type' => 'city', 'name' => 'Kano', 'slug' => 'kano', 'state' => 'Kano State', 'tier' => 2,
             'description' => 'Kano is the commercial centre of Northern Nigeria with a large population and growing middle class.',
             'latitude' => 12.0022, 'longitude' => 8.5920, 'household_estimate' => 500000],

            ['type' => 'city', 'name' => 'Enugu', 'slug' => 'enugu', 'state' => 'Enugu State', 'tier' => 2,
             'description' => 'Enugu is the capital of Enugu State and the commercial hub of South-East Nigeria.',
             'latitude' => 6.4403, 'longitude' => 7.4943, 'household_estimate' => 300000],

            ['type' => 'city', 'name' => 'Benin City', 'slug' => 'benin-city', 'state' => 'Edo State', 'tier' => 2,
             'description' => 'Benin City is the capital of Edo State with a rich cultural heritage and growing urban population.',
             'latitude' => 6.3350, 'longitude' => 5.6037, 'household_estimate' => 350000],

            ['type' => 'city', 'name' => 'Warri', 'slug' => 'warri', 'state' => 'Delta State', 'tier' => 2,
             'description' => 'Warri is a major oil industry city in Delta State with significant expatriate presence.',
             'latitude' => 5.5167, 'longitude' => 5.7500, 'household_estimate' => 200000],

            ['type' => 'city', 'name' => 'Owerri', 'slug' => 'owerri', 'state' => 'Imo State', 'tier' => 2,
             'description' => 'Owerri is the capital of Imo State and a growing commercial centre in South-East Nigeria.',
             'latitude' => 5.4840, 'longitude' => 7.0351, 'household_estimate' => 200000],

            ['type' => 'city', 'name' => 'Uyo', 'slug' => 'uyo', 'state' => 'Akwa Ibom State', 'tier' => 2,
             'description' => 'Uyo is the capital of Akwa Ibom State, known for its clean environment and growing urban development.',
             'latitude' => 5.0378, 'longitude' => 7.9085, 'household_estimate' => 200000],

            ['type' => 'city', 'name' => 'Calabar', 'slug' => 'calabar', 'state' => 'Cross River State', 'tier' => 2,
             'description' => 'Calabar is the capital of Cross River State, known for tourism and a growing urban population.',
             'latitude' => 4.9518, 'longitude' => 8.3220, 'household_estimate' => 250000],
        ];

        foreach ($locations as $loc) {
            $parentId = null;
            if (isset($loc['parent_slug'])) {
                $parent = SeoLocation::where('slug', $loc['parent_slug'])->first();
                $parentId = $parent?->id;
            }

            $data = [
                'type' => $loc['type'],
                'name' => $loc['name'],
                'slug' => $loc['slug'],
                'state' => $loc['state'] ?? null,
                'tier' => $loc['tier'],
                'parent_id' => $parentId,
                'description' => $loc['description'] ?? null,
                'demand_context' => $loc['demand_context'] ?? null,
                'notable_estates' => $loc['notable_estates'] ?? null,
                'latitude' => $loc['latitude'] ?? null,
                'longitude' => $loc['longitude'] ?? null,
                'household_estimate' => $loc['household_estimate'] ?? null,
                'is_active' => true,
            ];

            SeoLocation::updateOrCreate(
                ['slug' => $loc['slug'], 'parent_id' => $parentId],
                $data
            );
        }

        $this->command->info('SeoLocationSeeder: seeded ' . count($locations) . ' locations.');
    }
}
