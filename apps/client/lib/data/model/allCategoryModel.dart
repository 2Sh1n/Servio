class AllCategoryModel {
  final String? id;
  final String? name;
  final String? translated_name;
  final String? image;
  final List<AllCategoryModel>? subCategories;

  AllCategoryModel({
    this.id,
    this.name,
    this.translated_name,
    this.image,
    this.subCategories,
  });

  AllCategoryModel.fromJson(Map<String, dynamic> json)
      : id = json['id']?.toString() ?? '',
        name = json['name']?.toString() ?? '',
        translated_name = (json['translated_name']?.toString() ?? '').isNotEmpty
            ? json['translated_name']!.toString()
            : (json['name']?.toString() ?? ''),
        image = json['image']?.toString() ?? '',
        subCategories = (json['children'] as List?)
            ?.map((final e) => AllCategoryModel.fromJson(Map.from(e)))
            .toList();

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'translated_name': translated_name,
        'image': image,
        'children': subCategories,
      };
}
