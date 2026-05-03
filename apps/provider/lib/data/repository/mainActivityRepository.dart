import 'package:shared_preferences/shared_preferences.dart';

class MainActivityRepository {
  static const String key = 'grid_items';

  // Save grid item IDs to SharedPreferences
  static Future<void> savePositions(
    List<({String name, String icon})> items,
  ) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(
      key,
      items.map((item) => '${item.name}*${item.icon}').toList(),
    );
  }

  // Load grid item IDs from SharedPreferences and merge with default items
  // This ensures new items appear for users who update the app
  static Future<List<({String name, String icon})>> loadPositions(
    List<({String name, String icon})> defaultItems,
  ) async {
    final SharedPreferences prefs = await SharedPreferences.getInstance();
    final List<String> savedItems = prefs.getStringList(key) ?? <String>[];

    // If no saved items, return empty list (will use default)
    if (savedItems.isEmpty) {
      return [];
    }

    // Parse saved items
    final List<({String name, String icon})> savedList = savedItems.map((i) {
      final parts = i.split('*');
      return (name: parts.first, icon: parts.last);
    }).toList();

    // Get set of saved item names for quick lookup
    final Set<String> savedNames = savedList.map((e) => e.name).toSet();
    final Set<String> defaultNames = defaultItems.map((e) => e.name).toSet();

    // Filter out items that no longer exist in defaults
    final List<({String name, String icon})> filteredSaved =
        savedList.where((item) => defaultNames.contains(item.name)).toList();

    // Find new items that aren't in saved list and add them at the beginning
    final List<({String name, String icon})> newItems =
        defaultItems.where((item) => !savedNames.contains(item.name)).toList();

    // Return new items first, then saved items
    return [...newItems, ...filteredSaved];
  }
}
