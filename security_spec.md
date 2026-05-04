# UniAccommodate Security Specification

## Data Invariants
- Users must have a role assigned.
- Only landlords can create properties.
- Only students can create bookings.
- Bookings must reference valid properties.
- Messages must belong to a valid chat participants list.
- Admin status is verified against /admins/ collection.

## Dirty Dozen Payloads
1. Attempt to create a user with 'admin' role directly. (Denied)
2. Attempt to update another user's profile. (Denied)
3. Student attempting to create a property. (Denied)
4. Landlord attempting to verify their own property. (Denied)
5. Non-participant attempting to read chat messages. (Denied)
6. Updating a booking's status to 'paid' without actual payment (simulated by field name). (Denied)
7. Giant 1MB string in property title. (Denied)
8. Negative price in property listing. (Denied)
9. Injecting extra fields (ghost fields) into booking. (Denied)
10. Modifying 'createdAt' timestamp on update. (Denied)
11. Deleting someone else's property listing. (Denied)
12. Listing all users' private info. (Denied)

## Test Runner
(Tests will be implemented if required, for now focusing on implementation)
