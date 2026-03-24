<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // users
        Schema::table('users', function (Blueprint $table) {
            if (!$this->hasIndex('users', 'users_role_index'))
                $table->index('role');
            if (!$this->hasIndex('users', 'users_email_index'))
                $table->index('email');
        });

        // stores
        Schema::table('stores', function (Blueprint $table) {
            foreach (['status','province_id','city_id','store_category_id','is_featured','rating','visibility_expires_at'] as $col)
                if (!$this->hasIndex('stores', "stores_{$col}_index"))
                    $table->index($col);
        });

        // products
        Schema::table('products', function (Blueprint $table) {
            foreach (['store_id','product_category_id','brand_id','is_active','is_featured','total_sold','flash_until'] as $col)
                if (!$this->hasIndex('products', "products_{$col}_index"))
                    $table->index($col);
        });

        // carts & cart_items
        Schema::table('carts', function (Blueprint $table) {
            if (!$this->hasIndex('carts', 'carts_user_id_index'))
                $table->index('user_id');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            foreach (['cart_id','product_id','store_id'] as $col)
                if (!$this->hasIndex('cart_items', "cart_items_{$col}_index"))
                    $table->index($col);
        });

        // orders
        Schema::table('orders', function (Blueprint $table) {
            foreach (['user_id','status','payment_status','created_at'] as $col)
                if (!$this->hasIndex('orders', "orders_{$col}_index"))
                    $table->index($col);
        });
        Schema::table('store_orders', function (Blueprint $table) {
            foreach (['order_id','store_id','status'] as $col)
                if (!$this->hasIndex('store_orders', "store_orders_{$col}_index"))
                    $table->index($col);
        });

        // payments
        Schema::table('payments', function (Blueprint $table) {
            foreach (['user_id','order_id','status','method'] as $col)
                if (!$this->hasIndex('payments', "payments_{$col}_index"))
                    $table->index($col);
        });

        // reviews
        Schema::table('reviews', function (Blueprint $table) {
            if (!$this->hasIndex('reviews', 'reviews_user_id_index'))
                $table->index('user_id');
            if (!$this->hasIndex('reviews', 'reviews_reviewable_type_reviewable_id_index'))
                $table->index(['reviewable_type','reviewable_id']);
        });

        // store_employees
        Schema::table('store_employees', function (Blueprint $table) {
            foreach (['store_id','user_id','is_active'] as $col)
                if (!$this->hasIndex('store_employees', "store_employees_{$col}_index"))
                    $table->index($col);
        });

        // pos_sales
        Schema::table('pos_sales', function (Blueprint $table) {
            foreach (['store_id','user_id','payment_method'] as $col)
                if (!$this->hasIndex('pos_sales', "pos_sales_{$col}_index"))
                    $table->index($col);
        });

        // feedbacks
        Schema::table('feedbacks', function (Blueprint $table) {
            foreach (['status','type','user_id'] as $col)
                if (!$this->hasIndex('feedbacks', "feedbacks_{$col}_index"))
                    $table->index($col);
        });

        // product_stock
        Schema::table('product_stock', function (Blueprint $table) {
            if (!$this->hasIndex('product_stock', 'product_stock_product_id_index'))
                $table->index('product_id');
        });

        // stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            foreach (['product_id','type','user_id'] as $col)
                if (!$this->hasIndex('stock_movements', "stock_movements_{$col}_index"))
                    $table->index($col);
        });
    }

    public function down(): void {}

    private function hasIndex(string $table, string $index): bool
    {
        try {
            $indexes = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($indexes);
        } catch (\Throwable) {
            return false;
        }
    }
};
