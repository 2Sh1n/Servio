class CategoryModel {
  CategoryModel({
    required this.id,
    required this.name,
    required this.translatedName,
    required this.slug,
    required this.categoryImage,
    required this.backgroundDarkColor,
    required this.backgroundLightColor,
    this.totalProviders,
  });

  CategoryModel.fromJson(final Map<String?, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    name = json["name"]?.toString() ?? '';
    translatedName = (json["translated_name"]?.toString() ?? '').isNotEmpty
        ? json["translated_name"]!.toString()
        : (json["name"]?.toString() ?? '');
    slug = json["slug"]?.toString() ?? '';
    categoryImage = json["category_image"]?.toString() ?? '';
    backgroundLightColor = json['light_color']?.toString() ?? '';
    backgroundDarkColor = json['dark_color']?.toString() ?? '';
    totalProviders = json['total_providers']?.toString() ?? '';
  }

  String? id;
  String? name;
  String? translatedName;
  String? slug;
  String? categoryImage;
  String? backgroundDarkColor;
  String? backgroundLightColor;
  String? totalProviders;
}
