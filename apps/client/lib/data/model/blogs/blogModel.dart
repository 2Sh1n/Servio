class BlogTag {
  final String name;
  final String translatedName;
  final String slug;

  BlogTag({
    required this.name,
    required this.translatedName,
    required this.slug,
  });

  factory BlogTag.fromJson(Map<String, dynamic> json) {
    return BlogTag(
      name: json['name']?.toString() ?? '',
      translatedName: json['translated_name']?.toString() ?? '',
      slug: json['slug']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() => {
        'name': name,
        'translated_name': translatedName,
        'slug': slug,
      };
}

class BlogModel {
  final String? id;

  final String? title;
  final String? translatedTitle;
  final String? slug;
  final String? categoryId;
  final String? image;

  final String? description;
  final String? translatedDescription;

  // final String? shortDescription;
  final String? createdAt;
  final String? updatedAt;

  final String? categoryName;
  final String? translatedCategoryName;

  final List<BlogTag> tags;

  BlogModel({
    this.id,
    this.title,
    this.translatedTitle,
    this.slug,
    this.categoryId,
    this.image,
    this.description,
    this.translatedDescription,
    // this.shortDescription,
    this.createdAt,
    this.updatedAt,
    this.categoryName,
    this.translatedCategoryName,
    this.tags = const [],
  });

  BlogModel.fromJson(Map<String, dynamic> json)
      : id = json['id']?.toString() ?? '',
        title = json['title']?.toString() ?? '',
        translatedTitle =
            (json['translated_title']?.toString() ?? '').isNotEmpty
                ? json['translated_title']!.toString()
                : (json['title']?.toString() ?? ''),
        slug = json['slug']?.toString() ?? '',
        categoryId = json['category_id']?.toString() ?? '',
        image = json['image']?.toString() ?? '',
        description = json['description']?.toString() ?? '',
        translatedDescription =
            (json['translated_description']?.toString() ?? '').isNotEmpty
                ? json['translated_description']!.toString()
                : (json['description']?.toString() ?? ''),
        createdAt = json['created_at']?.toString() ?? '',
        updatedAt = json['updated_at']?.toString() ?? '',
        categoryName = json['description']?.toString() ?? '',
        translatedCategoryName =
            (json['translated_category_name']?.toString() ?? '').isNotEmpty
                ? json['translated_category_name']!.toString()
                : (json['description']?.toString() ?? ''),
        tags = (json['tags'] as List?)
                ?.map((tag) => BlogTag.fromJson(Map<String, dynamic>.from(tag)))
                .toList() ??
            [];

  Map<String, dynamic> toJson() => {
        'id': id,
        'title': title,
        'translated_title': translatedTitle,
        'slug': slug,
        'category_id': categoryId,
        'image': image,
        'description': description,
        'translated_description': translatedDescription,
        // 'short_description': shortDescription,
        'created_at': createdAt,
        'updated_at': updatedAt,
        'category_name': categoryName,
        'translated_category_name': translatedCategoryName,
        'tags': tags.map((tag) => tag.toJson()).toList(),
      };
}
