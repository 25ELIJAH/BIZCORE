<?php

namespace App\Livewire\Forms;

use App\Models\Product;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ProductForm extends Form
{
    public ?int $id = null;

    public ?int $product_category_id = null;

    public string $sku = '';

    public string $name = '';

    public string $description = '';

    public string $unit = 'pcs';

    public string $purchase_price = '0';

    public string $selling_price = '0';

    public string $tax_type = 'standard';

    public string $min_stock = '0';

    public bool $is_active = true;

    /** Only used when creating: becomes an "opening stock" movement. */
    public string $opening_quantity = '0';

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $companyId = app(CurrentCompany::class)->id();

        return [
            'product_category_id' => ['nullable', Rule::exists('product_categories', 'id')->where('company_id', $companyId)],
            'sku' => ['required', 'string', 'max:64', Rule::unique('products', 'sku')->where('company_id', $companyId)->ignore($this->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['required', 'string', 'max:20'],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'tax_type' => ['required', Rule::in(array_keys(Product::TAX_TYPES))],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:999999999999.999'],
            'is_active' => ['boolean'],
            'opening_quantity' => ['nullable', 'numeric', 'min:0', 'max:999999999999.999'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['product_category_id' => 'category', 'min_stock' => 'minimum stock'];
    }

    public function fillFrom(Product $product): void
    {
        $this->id = $product->id;
        $this->product_category_id = $product->product_category_id;
        $this->sku = $product->sku;
        $this->name = $product->name;
        $this->description = (string) $product->description;
        $this->unit = $product->unit;
        $this->purchase_price = (string) $product->purchase_price;
        $this->selling_price = (string) $product->selling_price;
        $this->tax_type = (string) $product->tax_type;
        $this->min_stock = (string) $product->min_stock;
        $this->is_active = $product->is_active;
    }

    /** @return array<string, mixed> attributes safe to pass to the model (no quantity) */
    public function attributes(): array
    {
        return [
            'product_category_id' => $this->product_category_id ?: null,
            'sku' => trim($this->sku),
            'name' => trim($this->name),
            'description' => $this->description ?: null,
            'unit' => trim($this->unit),
            'purchase_price' => $this->purchase_price,
            'selling_price' => $this->selling_price,
            'tax_type' => $this->tax_type,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
        ];
    }
}
