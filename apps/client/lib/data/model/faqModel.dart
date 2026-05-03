class Faqs {
  Faqs({
    required this.id,
    required this.question,
    required this.answer,
    required this.translatedQuestion,
    required this.translatedAnswer,
    required this.status,
    required this.createdAt,
  });

  Faqs.fromJson(final Map<String, dynamic> json) {
    id = json["id"]?.toString() ?? '';
    question = json["question"]?.toString() ?? '';
    translatedQuestion =
        (json["translated_question"]?.toString() ?? '').isNotEmpty
            ? json["translated_question"]!.toString()
            : (json["question"]?.toString() ?? '');

    answer = json["answer"]?.toString() ?? '';
    translatedAnswer = (json["translated_answer"]?.toString() ?? '').isNotEmpty
        ? json["translated_answer"]!.toString()
        : (json["answer"]?.toString() ?? '');

    status = json["status"]?.toString() ?? '';
    createdAt = json["created_at"]?.toString() ?? '';
  }

  late final String id;
  late final String question;
  late final String translatedQuestion;
  late final String answer;
  late final String translatedAnswer;
  late final String status;
  late final String createdAt;
}
