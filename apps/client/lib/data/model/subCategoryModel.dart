class SubCategory {
  SubCategory({
    this.id,
    this.name,
    this.translatedName,
    this.slug,
    this.categoryImage,
    this.darkBackgroundColor,
    this.lightBackgroundColor,
  });

  SubCategory.fromJson(final Map<String, dynamic> json)
      : id = json["id"]?.toString() ?? '',
        name = json["name"]?.toString() ?? '',
        translatedName = json["translated_name"]?.toString() ??
            json["name"]?.toString() ??
            '',
        slug = json["slug"]?.toString() ?? '',
        darkBackgroundColor = json["dark_color"]?.toString() ?? '',
        lightBackgroundColor = json["light_color"]?.toString() ?? '',
        categoryImage = json["category_image"]?.toString() ?? '';
  final String? id;
  final String? name;
  final String? translatedName;
  final String? slug;
  final String? categoryImage;
  final String? darkBackgroundColor;
  final String? lightBackgroundColor;
}
