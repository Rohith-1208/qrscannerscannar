import 'package:flutter_test/flutter_test.dart';
import 'package:college_maintenance_app/main.dart';
import 'package:college_maintenance_app/screens/login_screen.dart';

void main() {
  testWidgets('CollegeMaintenanceApp launches LoginScreen smoke test', (WidgetTester tester) async {
    await tester.pumpWidget(const CollegeMaintenanceApp(isLoggedIn: false));
    await tester.pumpAndSettle();

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Agent ID or Email'), findsOneWidget);
  });
}
