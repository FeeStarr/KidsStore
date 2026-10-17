<?php

namespace Database\Seeders;

use App\Models\CustomOrderColour;
use App\Models\CustomOrderEmbellishment;
use App\Models\CustomOrderFabric;
use App\Models\CustomOrderLength;
use App\Models\CustomOrderMeasurementField;
use App\Models\CustomOrderMeasurementGuide;
use App\Models\CustomOrderNeckline;
use App\Models\CustomOrderSkirt;
use App\Models\CustomOrderSleeve;
use App\Models\CustomOrderStyle;
use App\Models\CustomOrderWaist;
use Illuminate\Database\Seeder;

class CustomOrderLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStyles();
        $this->seedSleeves();
        $this->seedNecklines();
        $this->seedSkirts();
        $this->seedLengths();
        $this->seedWaists();
        $this->seedFabrics();
        $this->seedEmbellishments();
        $this->seedColours();
        $this->seedMeasurementFields();
        $this->seedMeasurementGuides();
        $this->seedFinishedGarmentGuides();
    }

    private function seedStyles(): void
    {
        $styles = ['A-line', 'Ball gown', 'Princess', 'Flare', 'Peplum', 'Empire waist', 'Straight', 'Layered', 'Other'];
        foreach ($styles as $i => $name) {
            CustomOrderStyle::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedSleeves(): void
    {
        $sleeves = ['Sleeveless', 'Short sleeve', 'Puff sleeve', 'Long sleeve', 'Bell sleeve', 'Flutter sleeve', 'Off-shoulder'];
        foreach ($sleeves as $i => $name) {
            CustomOrderSleeve::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedNecklines(): void
    {
        $necklines = ['Round', 'V-neck', 'Peter Pan', 'Square', 'Sweetheart', 'High neck'];
        foreach ($necklines as $i => $name) {
            CustomOrderNeckline::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedSkirts(): void
    {
        $skirts = ['Straight', 'Flared', 'Pleated', 'Layered', 'Gathered', 'Full skirt'];
        foreach ($skirts as $i => $name) {
            CustomOrderSkirt::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedLengths(): void
    {
        $lengths = ['Above knee', 'Knee length', 'Midi', 'Ankle length', 'Custom length'];
        foreach ($lengths as $i => $name) {
            CustomOrderLength::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedWaists(): void
    {
        $waists = ['Natural waist', 'High waist', 'Empire', 'Bow waist', 'Belted'];
        foreach ($waists as $i => $name) {
            CustomOrderWaist::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedFabrics(): void
    {
        $fabrics = ['Ankara', 'Lace', 'Tulle', 'Satin', 'Organza', 'Cotton', 'Brocade', 'Net', 'Other'];
        foreach ($fabrics as $i => $name) {
            CustomOrderFabric::firstOrCreate(
                ['name' => $name],
                ['availability' => 'available', 'sort_order' => $i + 1]
            );
        }
    }

    private function seedEmbellishments(): void
    {
        $embellishments = ['Lace', 'Pearls', 'Rhinestones', 'Flowers', 'Bows', 'Sequins', 'Appliqué', 'Beads', 'Embroidery'];
        foreach ($embellishments as $i => $name) {
            CustomOrderEmbellishment::firstOrCreate(['name' => $name], ['sort_order' => $i + 1]);
        }
    }

    private function seedColours(): void
    {
        $colours = [
            ['name' => 'Pink', 'hex' => '#FFC0CB'],
            ['name' => 'Red', 'hex' => '#FF0000'],
            ['name' => 'Blue', 'hex' => '#0000FF'],
            ['name' => 'Green', 'hex' => '#00FF00'],
            ['name' => 'Yellow', 'hex' => '#FFFF00'],
            ['name' => 'Purple', 'hex' => '#800080'],
            ['name' => 'Orange', 'hex' => '#FFA500'],
            ['name' => 'White', 'hex' => '#FFFFFF'],
            ['name' => 'Black', 'hex' => '#000000'],
            ['name' => 'Gold', 'hex' => '#FFD700'],
            ['name' => 'Silver', 'hex' => '#C0C0C0'],
            ['name' => 'Cream', 'hex' => '#FFFDD0'],
            ['name' => 'Peach', 'hex' => '#FFE5B4'],
            ['name' => 'Lavender', 'hex' => '#E6E6FA'],
            ['name' => 'Mint', 'hex' => '#98FF98'],
        ];
        foreach ($colours as $i => $colour) {
            CustomOrderColour::firstOrCreate(
                ['name' => $colour['name']],
                ['hex' => $colour['hex'], 'sort_order' => $i + 1]
            );
        }
    }

    private function seedMeasurementFields(): void
    {
        $fields = [
            ['name' => 'shoulder_width', 'label' => 'Shoulder Width'],
            ['name' => 'chest', 'label' => 'Chest / Bust'],
            ['name' => 'waist', 'label' => 'Waist'],
            ['name' => 'hip', 'label' => 'Hip'],
            ['name' => 'armhole', 'label' => 'Armhole'],
            ['name' => 'sleeve_length', 'label' => 'Sleeve Length'],
            ['name' => 'shoulder_to_waist', 'label' => 'Shoulder to Waist'],
            ['name' => 'waist_to_knee', 'label' => 'Waist to Knee'],
            ['name' => 'full_dress_length', 'label' => 'Full Dress Length'],
            ['name' => 'neck_circumference', 'label' => 'Neck Circumference'],
        ];
        foreach ($fields as $i => $field) {
            CustomOrderMeasurementField::firstOrCreate(
                ['name' => $field['name']],
                ['label' => $field['label'], 'sort_order' => $i + 1]
            );
        }
    }

    private function seedMeasurementGuides(): void
    {
        $guides = [
            [
                'measurement_type' => 'shoulder_width',
                'name' => 'Shoulder Width',
                'description' => 'Measure from one shoulder point across the back to the other shoulder point, keeping the tape measure straight and level.',
            ],
            [
                'measurement_type' => 'chest',
                'name' => 'Chest / Bust',
                'description' => 'Measure around the fullest part of the child\'s chest while keeping the measuring tape comfortably snug.',
            ],
            [
                'measurement_type' => 'waist',
                'name' => 'Waist',
                'description' => 'Measure around the natural waistline, just above the belly button. Keep the tape measure comfortably loose.',
            ],
            [
                'measurement_type' => 'hip',
                'name' => 'Hip',
                'description' => 'Measure around the fullest part of the hips, keeping the tape measure level.',
            ],
            [
                'measurement_type' => 'armhole',
                'name' => 'Armhole',
                'description' => 'Measure from the top of the shoulder, down around the armpit, and back up to the starting point.',
            ],
            [
                'measurement_type' => 'sleeve_length',
                'name' => 'Sleeve Length',
                'description' => 'Measure from the shoulder point down to the desired sleeve end point.',
            ],
            [
                'measurement_type' => 'shoulder_to_waist',
                'name' => 'Shoulder to Waist',
                'description' => 'Measure from the shoulder point straight down to the natural waistline.',
            ],
            [
                'measurement_type' => 'waist_to_knee',
                'name' => 'Waist to Knee',
                'description' => 'Measure from the natural waistline down to the middle of the knee.',
            ],
            [
                'measurement_type' => 'full_dress_length',
                'name' => 'Full Dress Length',
                'description' => 'Measure from the shoulder point straight down to the desired dress hem length.',
            ],
            [
                'measurement_type' => 'neck_circumference',
                'name' => 'Neck Circumference',
                'description' => 'Measure around the base of the neck, leaving room for one finger between the tape and skin.',
            ],
        ];
        foreach ($guides as $i => $guide) {
            // Keyed on basis too: the same measurement type now has separate copy for
            // body and finished-garment figures.
            CustomOrderMeasurementGuide::firstOrCreate(
                ['measurement_type' => $guide['measurement_type'], 'basis' => 'body'],
                array_merge($guide, ['basis' => 'body', 'sort_order' => $i + 1])
            );
        }
    }

    /**
     * How-to-measure copy for finished-garment figures.
     *
     * These describe the garment, not the child's body, so measuring a child with them
     * would be wrong. Written conservatively; review the wording with your tailor
     * before relying on it.
     */
    private function seedFinishedGarmentGuides(): void
    {
        $guides = [
            [
                'measurement_type' => 'shoulder_width',
                'name' => 'Shoulder Width (garment)',
                'description' => 'Lay a similar frock flat with no stretching. Measure straight across the shoulders from seam to seam.',
            ],
            [
                'measurement_type' => 'chest',
                'name' => 'Chest / Bust (garment)',
                'description' => 'Lay the garment flat. Measure straight across the widest part, usually about 2cm below the armhole seam.',
            ],
            [
                'measurement_type' => 'waist',
                'name' => 'Waist (garment)',
                'description' => 'Lay the garment flat. Measure straight across the narrowest part of the waist seam.',
            ],
            [
                'measurement_type' => 'hip',
                'name' => 'Hip (garment)',
                'description' => 'Lay the garment flat. Measure straight across the widest part of the hip seam.',
            ],
            [
                'measurement_type' => 'armhole',
                'name' => 'Armhole (garment)',
                'description' => 'Measure around the inside of the armhole seam of a similar garment, following the seam.',
            ],
            [
                'measurement_type' => 'sleeve_length',
                'name' => 'Sleeve Length (garment)',
                'description' => 'Measure from the shoulder seam to the end of the sleeve on a similar laid-flat garment.',
            ],
            [
                'measurement_type' => 'shoulder_to_waist',
                'name' => 'Shoulder to Waist (garment)',
                'description' => 'Lay the garment flat and measure straight down from the shoulder seam to the waist seam.',
            ],
            [
                'measurement_type' => 'waist_to_knee',
                'name' => 'Waist to Knee (garment)',
                'description' => 'Measure straight down the garment from the waist seam to the finished hem at knee level.',
            ],
            [
                'measurement_type' => 'full_dress_length',
                'name' => 'Full Dress Length (garment)',
                'description' => 'Measure straight down the front of a similar frock, from the shoulder seam to the finished hem.',
            ],
            [
                'measurement_type' => 'neck_circumference',
                'name' => 'Neck Circumference (garment)',
                'description' => 'Measure around the neckline of a similar garment, following the seam.',
            ],
        ];

        foreach ($guides as $i => $guide) {
            CustomOrderMeasurementGuide::firstOrCreate(
                ['measurement_type' => $guide['measurement_type'], 'basis' => 'finished_garment'],
                array_merge($guide, ['basis' => 'finished_garment', 'sort_order' => $i + 1])
            );
        }
    }
}
