<?php

namespace Database\Seeders;

use App\Models\LabSpace;
use App\Models\User;
use App\Services\Media\MediaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LabSpaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $labSpaces = [
            [
                'name' => 'Wet Lab',
                'slug' => 'wet-lab',
                'type' => 'wet_lab',
                'description' => 'A fully equipped wet laboratory designed for biological, chemical, and biochemical research. Features include fume hoods, biosafety cabinets, PCR machines, centrifuges, and specialized workbenches for handling liquids, chemicals, and biological samples safely.',
                'capacity' => 1,
                'hourly_rate' => 500.00,
                'equipment_list' => [
                    'Fume hoods (3)',
                    'Biosafety cabinet (Class II)',
                    'PCR thermal cycler',
                    'Centrifuges (micro and benchtop)',
                    'Electrophoresis equipment',
                    'UV transilluminator',
                    'pH meters and balances',
                    'Refrigerators and freezers (-20°C, -80°C)',
                    'Chemical storage cabinets',
                    'Emergency shower and eyewash station',
                ],
                'safety_requirements' => [
                    'Lab Safety Training Certificate',
                    'Chemical Handling Training',
                    'Closed-toe shoes required',
                    'Lab coat and safety goggles required',
                    'No food or drinks in lab',
                ],
                'county' => 'Nairobi',
                'location' => 'Main Campus, Block B',
                'is_available' => true,
                'available_from' => '08:00',
                'available_until' => '18:00',
                'opens_at' => '09:00',
                'closes_at' => '17:00',
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'timezone' => 'Africa/Nairobi',
            ],
            [
                'name' => 'Dry Lab',
                'slug' => 'dry-lab',
                'type' => 'dry_lab',
                'description' => 'A computational and analytical laboratory focused on data analysis, bioinformatics, software development, and theoretical research. Equipped with high-performance computing workstations, large displays for data visualization, and collaborative workspace areas.',
                'capacity' => 2,
                'hourly_rate' => 300.00,
                'equipment_list' => [
                    'High-performance computing workstations (10)',
                    'Large 4K displays for data visualization',
                    'High-speed internet (1 Gbps)',
                    'Video conferencing equipment',
                    'Whiteboard walls',
                    'Collaborative workspace areas',
                    'Presentation screen and projector',
                    'Noise-canceling booths (2)',
                    'Ergonomic chairs and standing desks',
                    'Air conditioning and backup power',
                ],
                'safety_requirements' => [
                    'Basic Lab Orientation',
                    'Computer Lab Usage Agreement',
                    'No food or drinks near equipment',
                ],
                'county' => 'Nairobi',
                'location' => 'Innovation Hub, Floor 4',
                'is_available' => true,
                'available_from' => '08:00',
                'available_until' => '20:00',
                'opens_at' => '09:00',
                'closes_at' => '20:00',
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'timezone' => 'Africa/Nairobi',
            ],
            [
                'name' => 'Greenhouse',
                'slug' => 'greenhouse',
                'type' => 'greenhouse',
                'description' => 'A controlled environment greenhouse for plant science, agricultural research, and sustainability projects. Features automated climate control, irrigation systems, and dedicated areas for seedling propagation, growth experiments, and vertical farming research.',
                'capacity' => 1,
                'hourly_rate' => 400.00,
                'equipment_list' => [
                    'Automated climate control system',
                    'Drip irrigation system',
                    'Grow lights (LED full spectrum)',
                    'Seedling propagation benches',
                    'Vertical farming racks',
                    'Soil testing equipment',
                    'Potting station with supplies',
                    'Plant monitoring sensors',
                    'Composting area',
                    'Tool storage and cleaning station',
                ],
                'safety_requirements' => [
                    'Greenhouse Safety Orientation',
                    'Plant Handling and Pesticide Safety',
                    'Closed-toe shoes required',
                    'Sun protection recommended',
                    'Allergen awareness',
                ],
                'county' => 'Kiambu',
                'location' => 'Agricultural Research Plot C',
                'is_available' => true,
                'available_from' => '06:00',
                'available_until' => '18:00',
                'opens_at' => '06:00',
                'closes_at' => '18:00',
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'timezone' => 'Africa/Nairobi',
            ],
            [
                'name' => 'Mobile Lab Unit',
                'slug' => 'mobile-lab-unit',
                'type' => 'mobile_lab',
                'description' => 'A fully equipped mobile laboratory unit designed for field research, community outreach, and off-site experiments. Can be deployed to remote locations for environmental sampling, field testing, and educational demonstrations. Booking includes the vehicle and basic equipment setup.',
                'capacity' => 1,
                'hourly_rate' => 450.00,
                'equipment_list' => [
                    'Mobile lab vehicle',
                    'Portable PCR machine',
                    'Field microscopes',
                    'Sample collection kits',
                    'Water testing equipment',
                    'Portable centrifuge',
                    'GPS and field mapping tools',
                    'Portable power generator',
                    'First aid and emergency kit',
                    'Field documentation equipment',
                ],
                'safety_requirements' => [
                    'Field Research Safety Training',
                    'Valid Driver\'s License (for driver)',
                    'First Aid Certification (recommended)',
                    'Personal Protective Equipment (PPE) usage',
                    'Emergency communication protocol knowledge',
                ],
                'county' => 'Mobile',
                'location' => 'Various Locations',
                'is_available' => true,
                'available_from' => '07:00',
                'available_until' => '19:00',
                'opens_at' => '07:00',
                'closes_at' => '19:00',
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'timezone' => 'Africa/Nairobi',
            ],
        ];

        $admin = User::first();

        foreach ($labSpaces as $index => $spaceData) {
            $lab = LabSpace::updateOrCreate(
                ['slug' => $spaceData['slug']],
                $spaceData
            );

            // Assign seed images (CAS / R2)
            if (app()->environment('local', 'testing', 'staging')) {
                $labImages = [
                    'seed-images/biotech-lab.png',
                    'seed-images/tech-hub.png',
                    'seed-images/robotics-camp.png',
                    'seed-images/nature-conservation.png',
                ];

                $imagePath = $labImages[$index % count($labImages)];
                $absolutePath = storage_path('app/public/' . $imagePath);

                if (file_exists($absolutePath)) {
                    try {
                        /** @var MediaService $mediaService */
                        $mediaService = app(MediaService::class);
                        $media = $mediaService->registerFile(
                            $admin,
                            $absolutePath,
                            basename($imagePath),
                            [
                                'visibility' => 'public',
                                'root_type' => 'public',
                                'path' => ['lab-spaces', $lab->slug],
                            ]
                        );

                        $lab->setFeaturedMedia($media->id);
                        
                        // Add some gallery images too
                        $lab->addGalleryMedia([$media->id]);
                    } catch (\Exception $e) {
                        $this->command->warn('Failed to register CAS media for lab: ' . $lab->name);
                    }
                }
            }
        }

        $this->command->info('Lab spaces seeded successfully.');
    }
}
