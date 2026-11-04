<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product Categories
    |--------------------------------------------------------------------------
    |
    | Single source of truth for product categories. The `id` is what the
    | Alpine components store, `label` is what the Add Product form, the
    | Excel template dropdowns and the import parser all display.
    |
    */

    'categories' => [
        ['id' => 'spirits', 'label' => 'Spirits', 'icon' => '🥃'],
        ['id' => 'beer', 'label' => 'Beer', 'icon' => '🍺'],
        ['id' => 'wine', 'label' => 'Wine', 'icon' => '🍷'],
        ['id' => 'softdrinks', 'label' => 'Soft Drinks', 'icon' => '🥤'],
        ['id' => 'water', 'label' => 'Water', 'icon' => '💧'],
        ['id' => 'food', 'label' => 'Food', 'icon' => '🍔'],
        ['id' => 'supplies', 'label' => 'Supplies', 'icon' => '🧂'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Units
    |--------------------------------------------------------------------------
    */

    'units' => ['Units', 'Bottle', 'Pack', 'Bag', 'Box', 'Kg', 'Carton', 'Crate', 'Sack'],

    /*
    |--------------------------------------------------------------------------
    | Counters (Bar / storage points)
    |--------------------------------------------------------------------------
    */

    'counters' => ['Main Counter', 'VIP Counter', 'Pool Bar', 'Lounge Bar'],

    /*
    |--------------------------------------------------------------------------
    | Product Import — Spreadsheet Columns
    |--------------------------------------------------------------------------
    |
    | These are the columns that appear in the downloadable Excel template,
    | one per column, in this order. The importer matches headers by name, so
    | column order is not critical, but renaming them will break the match.
    |
    | Supported keys per column:
    |   key        - product attribute this column maps to
    |   label      - header text
    |   type       - text | number | decimal
    |   required   - whether the import rejects a blank value
    |   width      - Excel column width in characters
    |   example    - sample value used in the Instructions sheet
    |   help       - short hint shown in the Instructions sheet
    |
    | Note: Category, Unit and Counter are deliberately NOT spreadsheet
    | columns. They are applied to the whole import at once, chosen in the
    | Import dialog (see `import_assignments`) or defaulted (see
    | `import_defaults`). That keeps the file to seven, unambiguous columns.
    |
    | Cross-column rules (e.g. selling price must not be below buying price)
    | live in `import_rules` so they are enforced in one place and reported to
    | the user per row in the import preview.
    |
    |
    */

    'import_columns' => [
        [
            'key' => 'name',
            'label' => 'Product Name',
            'type' => 'text',
            'required' => true,
            'width' => 32,
            'example' => 'Konyagi 350ml',
            'help' => 'Full product name, e.g. "Konyagi 350ml". Must be unique.',
        ],
        [
            'key' => 'stock',
            'label' => 'Stock Quantity',
            'type' => 'number',
            'required' => true,
            'width' => 16,
            'example' => 48,
            'help' => 'Current quantity on hand. Whole numbers only.',
        ],
        [
            'key' => 'reorder',
            'label' => 'Reorder Level',
            'type' => 'number',
            'required' => false,
            'width' => 15,
            'example' => 20,
            'help' => 'Optional. Leave blank if you do not track a reorder point — it defaults to 0.',
        ],
        [
            'key' => 'buyPrice',
            'label' => 'Buying Price (TSh)',
            'type' => 'decimal',
            'required' => true,
            'width' => 19,
            'example' => 4000,
            'help' => 'Unit cost paid to the supplier. No commas.',
        ],
        [
            'key' => 'sellPrice',
            'label' => 'Selling Price (TSh)',
            'type' => 'decimal',
            'required' => true,
            'width' => 20,
            'example' => 7000,
            'help' => 'Price charged to customers. Should exceed buying price.',
        ],
        [
            'key' => 'expiryDate',
            'label' => 'Expiry Date',
            'type' => 'date',
            'required' => false,
            'width' => 16,
            'example' => '2027-12-31',
            'format' => 'yyyy-mm-dd',
            'help' => 'Optional. Format YYYY-MM-DD. Leave blank for non-perishable stock.',
        ],
        [
            'key' => 'sup',
            'label' => 'Supplier',
            'type' => 'text',
            'required' => false,
            'width' => 24,
            'example' => 'Distillers Ltd',
            'help' => 'Optional. Supplier name, used when reordering stock.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Import — Applied To The Whole File
    |--------------------------------------------------------------------------
    |
    | These fields are not per-product, so they are picked once in the Import
    | dialog and applied to every row. Rendered as dropdowns in the UI, so the
    | options are shared with the Add Product form.
    |
    */

    'import_assignments' => [
        [
            'key' => 'cat',
            'label' => 'Category',
            'default' => 'Spirits',
            'options' => ['Spirits', 'Beer', 'Wine', 'Soft Drinks', 'Water', 'Food', 'Supplies'],
        ],
        [
            'key' => 'counter',
            'label' => 'Counter',
            'default' => 'Main Counter',
            'options' => ['Main Counter', 'VIP Counter', 'Pool Bar', 'Lounge Bar'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Import — Fixed Values
    |--------------------------------------------------------------------------
    |
    | Fields that are not worth asking about during an import. Applied to every
    | row as-is. Unit is the only one: it is the generic "sell each" unit, and
    | it can always be corrected afterwards per product.
    |
    */

    'import_defaults' => [
        'unit' => 'Units',
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Import — Cross-Column Rules
    |--------------------------------------------------------------------------
    |
    | Rules that compare two columns in the same row. They run after per-cell
    | validation and mark the row invalid, so a bad row is skipped rather than
    | silently creating a product that loses money on every sale.
    |
    | Supported keys per rule:
    |   key         - stable id, referenced by the error message builder
    |   type        - currently only `less_than` is supported
    |   left        - column key that must stay below `right`
    |   right       - column key it is compared against
    |   message     - shown per row in the preview, %left%/%right% substituted
    |   severity    - error (invalid row) | warning (imported, but flagged)
    |
    */

    'import_rules' => [
        [
            'key' => 'margin',
            'type' => 'less_than',
            'left' => 'sellPrice',
            'right' => 'buyPrice',
            'message' => 'Selling Price cannot be lower than the Buying Price',
            'severity' => 'error',
        ],
        [
            'key' => 'already_expired',
            'type' => 'date_before',
            'left' => 'expiryDate',
            'right' => '@today',
            'message' => '%left% is already in the past',
            'severity' => 'warning',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Limits
    |--------------------------------------------------------------------------
    */

    'import' => [
        'max_rows' => 2000,
        'max_size_kb' => 5120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Import — Demo Rows
    |--------------------------------------------------------------------------
    |
    | Example products pre-filled on the first rows of the entry area so a
    | first-time user can see exactly how each column is meant to be filled
    | before typing their own. They are deliberately varied to show the
    | optional fields in both states: one row leaves the Expiry Date blank
    | (non-perishable), one leaves Reorder Level and Supplier blank.
    |
    | Keys match `import_columns`. Leave a value as null to leave that cell
    | blank. A date may be given as an absolute YYYY-MM-DD string or as a
    | relative offset such as "+6 months", which is resolved against today
    | when the template is built — that keeps the samples from silently
    | going stale and starting to trigger the expired-date warning.
    |
    | These rows are real data as far as the importer is concerned. The
    | Instructions sheet tells the user to overwrite them or clear their
    | contents. Note that sheet protection blocks deleting whole rows, so
    | "clear the contents" is the removal route that actually works.
    |
    */

    'import_demo_rows' => [
        [
            'name' => 'Konyagi 350ml',
            'stock' => 48,
            'reorder' => 20,
            'buyPrice' => 4000,
            'sellPrice' => 7000,
            'expiryDate' => null,
            'sup' => 'Distillers Ltd',
        ],
        [
            'name' => 'Bottled Water 500ml',
            'stock' => 60,
            'reorder' => null,
            'buyPrice' => 800,
            'sellPrice' => 1500,
            'expiryDate' => null,
            'sup' => null,
        ],
        [
            'name' => 'Imala Beef 1kg',
            'stock' => 15,
            'reorder' => 5,
            'buyPrice' => 9500,
            'sellPrice' => 14000,
            'expiryDate' => '+6 months',
            'sup' => 'Imala Meats',
        ],
    ],

];
