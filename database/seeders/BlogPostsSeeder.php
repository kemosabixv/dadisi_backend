<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\County;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogPostsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Biotechnology Categories if they don't exist
        $categories = [
            'Genomics',
            'Synthetic Biology',
            'Bioinformatics',
            'Agricultural Biotechnology',
            'Medical Biotechnology',
            'Marine Biotechnology',
            'Industrial Biotechnology',
            'Forensic Science',
            'Neuroinformatics',
            'Environmental Sustainability',
            'Immunology',
        ];

        $categoryModels = [];
        foreach ($categories as $cat) {
            $categoryModels[] = Category::firstOrCreate(
                ['slug' => Str::slug($cat)],
                ['name' => $cat, 'description' => "Deep dive into $cat and its impact on the future."]
            );
        }

        // 2. Create Biotechnology Tags
        $tags = [
            'CRISPR',
            'DNA Sequencing',
            'GMO',
            'Vaccines',
            'Bioremediation',
            'Stem Cells',
            'Genetic Engineering',
            'Bioplastics',
            'DNA Profiling',
            'Biofuels',
            'Microbiome',
            'Data Science',
            'CRISPR-Cas12',
            'Nanobiotechnology',
        ];

        $tagModels = [];
        foreach ($tags as $tagName) {
            $tagModels[] = Tag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
        }

        // 3. Find target users
        $superAdmin = User::where('email', 'superadmin@dadisilab.com')->first();
        $contentEditor = User::where('email', 'admin@dadisilab.com')->first();
        $studentUser = User::where('email', 'student@dadisilab.com')->first();
        $premiumUser = User::where('email', 'jane.smith@dadisilab.com')->first();

        if (!$superAdmin || !$contentEditor || !$studentUser || !$premiumUser) {
            $this->command->error('Target users not found. Please run AdminUserSeeder first.');
            return;
        }

        // Note: Author role was removed - authoring access is now controlled via subscriptions

        $county = County::first() ?? County::create(['name' => 'Nairobi']);

        // 4. Define Post Data (Total 20)
        $postsData = [
            // Super Admin (3)
            [
                'user_id' => $superAdmin->id,
                'title' => 'Revolutionizing Medicine with CRISPR Gene Editing',
                'body' => '<p>CRISPR-Cas9 has transformed our ability to edit genomes precisely. This article explores how gene therapy is moving from experimental phases to life-saving treatments for genetic disorders.</p>',
                'status' => 'published',
                'is_featured' => true,
            ],
            [
                'user_id' => $superAdmin->id,
                'title' => 'The Genomics Era: Mapping the Human Future',
                'body' => '<p>With the cost of sequencing dropping rapidly, personalized medicine is becoming a reality. We analyze what this means for population health in Kenya.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $superAdmin->id,
                'title' => 'Ethics in Genetic Engineering: Where Do We Draw the Line?',
                'body' => '<p>As we gain the power to design life, the ethical implications grow. This post discusses the regulatory landscape for biotechnology in East Africa.</p>',
                'status' => 'draft',
                'is_featured' => false,
            ],

            // Content Editor (5)
            [
                'user_id' => $contentEditor->id,
                'title' => 'The Future of Sustainable Agriculture: GMOs in Africa',
                'body' => '<p>Genetically Modified Organisms offer solutions to drought and pests. We look at the latest developments in Bt Cotton and drought-resistant maize.</p>',
                'status' => 'published',
                'is_featured' => true,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Synthetic Biology: Designing Life Forms of Tomorrow',
                'body' => '<p>Synthetic biology combines engineering and biology to build new biological parts. From biofuels to bioplastics, the possibilities are endless.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Bioinformatics: Decoding the Language of the Genome',
                'body' => '<p>Big data meets biology. Learn how computational tools are helping scientists understand complex biological systems.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Biopharma Breakthroughs: The Rise of mRNA Vaccines',
                'body' => '<p>Following the success of COVID-19 vaccines, mRNA technology is being targeted at Malaria and HIV. A look at the regional manufacturing hubs.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Environmental Biotech: Microbes Cleaning Our Oceans',
                'body' => '<p>Bioremediation uses microorganisms to degrade environmental pollutants. Discover how local researchers are using bacteria to tackle plastic waste.</p>',
                'status' => 'draft',
                'is_featured' => false,
            ],

            // Student Subscriber (1)
            [
                'user_id' => $studentUser->id,
                'title' => 'Stem Cells: A Student Perspective on Regenerative Medicine',
                'body' => '<p>As a student researcher, I\'ve seen the potential of stem cells in tissue engineering. This post summarizes the key takeaways from the recent Nairobi Biotech Summit.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],

            // Premium Subscriber (1)
            [
                'user_id' => $premiumUser->id,
                'title' => 'Personalized Medicine: Tailoring Treatments to Your DNA',
                'body' => '<p>Pharmacogenomics allows doctors to prescribe the right drug at the right dose. Why this is the next frontier for healthcare investment in Africa.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],

            // NEW POSTS (10 more)
            [
                'user_id' => $superAdmin->id,
                'title' => 'Forensic DNA Profiling: Solving Crimes in the 21st Century',
                'body' => '<p>How DNA profiling has revolutionized the criminal justice system in Kenya. A look at current forensic labs and future capabilities.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $superAdmin->id,
                'title' => 'Neuroinformatics: Mapping the Mysteries of the Human Brain',
                'body' => '<p>Computational tools are allowing us to model brain activity like never before. Exploring the intersection of neuroscience and big data.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $superAdmin->id,
                'title' => 'Environmental Sustainability through Bioremediation',
                'body' => '<p>Targeting oil spills and heavy metal contamination with specialized microorganisms. A biotechnical approach to environmental restoration.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Marine Biotechnology: Treasures from the Kenyan Coast',
                'body' => '<p>Exploring the unique biodiversity of the Indian Ocean for potential bioactive compounds and new pharmaceutical leads.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Industrial Biotech: Fermenting the Future of Kenyan Industry',
                'body' => '<p>Using enzymes and microorganisms for industrial processes, reducing emissions and increasing efficiency in local factories.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'The Rise of Biofuels: Powering Kenya with Algae',
                'body' => '<p>Investigating the potential of third-generation biofuels from algae to provide sustainable energy solutions for the transport sector.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Immunology Breakthroughs: Developing Next-Gen Antibodies',
                'body' => '<p>A deep dive into monoclonal antibodies and their use in treating chronic diseases and emerging infections in the region.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $contentEditor->id,
                'title' => 'Nanobiotechnology in Cancer Detection: Tiny Tools, Big Impact',
                'body' => '<p>How nanotechnology is enabling earlier and more precise cancer diagnostics through targeted biosensors and imaging agents.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $premiumUser->id,
                'title' => 'Microbiome Research: The Secret Life of Our Gut Bacteria',
                'body' => '<p>Understanding the role of the human microbiome in health and disease. Why diet and genetics play a crucial role in our bacterial makeup.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
            [
                'user_id' => $studentUser->id,
                'title' => 'Bioplastics: A Bio-based Solution to Kenya\'s Pollution',
                'body' => '<p>Exploring biopolymers derived from agricultural waste as a sustainable alternative to conventional plastics in local markets.</p>',
                'status' => 'published',
                'is_featured' => false,
            ],
        ];

        foreach ($postsData as $pData) {
            $slug = Str::slug($pData['title']);
            $post = Post::updateOrCreate(
                ['slug' => $slug],
                [
                    'user_id' => $pData['user_id'],
                    'county_id' => $county->id,
                    'title' => $pData['title'],
                    'excerpt' => Str::limit(strip_tags($pData['body']), 150),
                    'body' => $pData['body'],
                    'status' => $pData['status'],
                    'is_featured' => $pData['is_featured'],
                    'published_at' => $pData['status'] === 'published' ? now() : null,
                    'meta_title' => Str::limit($pData['title'], 57),
                    'meta_description' => Str::limit(strip_tags($pData['body']), 157),
                ]
            );

            // Randomly assign 1-2 categories
            $post->categories()->sync(
                collect($categoryModels)->pluck('id')->random(rand(1, 2))
            );

            // Randomly assign 2-3 tags
            $post->tags()->sync(
                collect($tagModels)->pluck('id')->random(rand(2, 3))
            );

            // 5. Assign Seed Images (Local/Testing only)
            if (app()->environment('local', 'testing', 'staging')) {
                $seedImages = [
                    'seed-images/biotech-lab.png',
                    'seed-images/genomics-viz.png',
                    'seed-images/tech-hub.png',
                ];
                
                // Assign a random seed image
                $randomImage = $seedImages[array_rand($seedImages)];
                $post->update(['hero_image_path' => $randomImage]);

                // Create a Media record to exercise the media system
                if (file_exists(storage_path('app/public/' . $randomImage))) {
                    try {
                        $media = \App\Models\Media::firstOrCreate(
                            ['file_path' => $randomImage],
                            [
                                'user_id' => $post->user_id,
                                'file_name' => basename($randomImage),
                                'mime_type' => 'image/png',
                                'file_size' => filesize(storage_path('app/public/' . $randomImage)),
                                'type' => 'image',
                                'is_public' => true,
                            ]
                        );
                        
                        // Attach to post via post_media table
                        $post->media()->syncWithoutDetaching([$media->id]);
                    } catch (\Exception $e) {
                        $this->command->warn('Failed to link media for post: ' . $post->title);
                    }
                }
            }
        }

        $this->command->info('20 Biotechnology posts seeded successfully!');
    }
}
