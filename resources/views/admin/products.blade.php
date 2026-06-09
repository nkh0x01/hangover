@include('admin.partials.stub', [
    'title' => 'Products',
    'subtitle' => 'gadget.ge WooCommerce კატალოგი + AI რეკომენდაციები',
    'heading' => 'Product catalog',
    'body' => 'სრული product-list + AI recommendation tuner. Sync ხდება ყოველ 15 წუთში cron-ით WooCommerce-დან.',
    'links' => ['/admin/integrations#woocommerce' => 'WooCommerce credentials'],
])
